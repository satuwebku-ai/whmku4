<?php

namespace App\Services\Mail;

use App\Models\ActivityLog;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mengubah satu email masuk menjadi balasan tiket, pesan Live Chat, atau
 * thread di Inbox Email.
 *
 *  - Subjek memuat nomor tiket (TKT-2026-0001) DAN pengirim = email klien
 *    pemilik tiket  -> jadi balasan tiket tersebut.
 *  - Subjek memuat [CHAT-12] DAN pengirim = email percakapan itu -> masuk
 *    ke percakapan Live Chat yang sama (balasan atas email dari widget chat).
 *  - Selain itu -> Inbox Email: lanjut thread yang ada (token [MAIL-12],
 *    header In-Reply-To/References, atau pengirim + subjek yang sama), atau
 *    thread baru.
 *
 * Pengirim wajib cocok dengan pemilik tiket/percakapan, supaya orang lain
 * tidak bisa menyusupkan pesan ke tiket klien hanya dengan menebak nomornya.
 */
class InboundMailProcessor
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'log', 'zip'];

    /**
     * @return string hasil: ticket:ID | chat:ID | ignored:alasan
     */
    public function process(MimeMessage $mail): string
    {
        $from = $mail->from();
        $email = $from['email'];

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'ignored:pengirim tidak valid';
        }

        if ($mail->isAutomated()) {
            return 'ignored:email otomatis/bounce';
        }

        if ($mail->failsSenderAuth()) {
            return 'ignored:pengirim gagal verifikasi SPF/DKIM/DMARC';
        }

        if (in_array($email, $this->ownAddresses(), true)) {
            return 'ignored:dari alamat sendiri';
        }

        $subject = $mail->subject();
        $body = trim($mail->body());

        if ($body === '' && $mail->attachments === []) {
            return 'ignored:isi kosong';
        }

        // 1) Balasan tiket
        if (preg_match('/\bTKT-\d{4}-\d{4,}\b/i', $subject, $m)) {
            $ticket = Ticket::where('ticket_number', strtoupper($m[0]))->with('client')->first();

            if ($ticket && $ticket->client && strtolower($ticket->client->email) === $email) {
                return $this->appendToTicket($ticket, $mail, $body);
            }
        }

        // 2) Lanjutan percakapan Live Chat berdasarkan token di subjek
        if (preg_match('/\[CHAT-(\d+)\]/i', $subject, $m)) {
            $candidate = ChatConversation::find((int) $m[1]);

            if ($candidate && strtolower((string) $candidate->email) === $email) {
                return $this->appendToChat($candidate, $mail, $body);
            }
        }

        // 3) Selain itu -> Inbox Email
        return $this->appendToMailThread($mail, $from, $subject, $body);
    }

    private function appendToTicket(Ticket $ticket, MimeMessage $mail, string $body): string
    {
        $reply = $ticket->replies()->create([
            'client_id' => $ticket->client_id,
            'message' => Str::limit($body !== '' ? $body : '(lampiran email)', 15000, ''),
        ]);

        foreach ($mail->attachments as $att) {
            if (! $this->allowed($att['name'])) {
                continue;
            }

            $path = 'ticket-attachments/' . Str::random(40) . '.' . $this->extension($att['name']);
            Storage::disk('local')->put($path, $att['content']);

            $reply->attachments()->create([
                'path' => $path,
                'original_name' => Str::limit($att['name'], 200, ''),
                'mime_type' => $att['mime'],
                'size' => strlen($att['content']),
            ]);
        }

        // Tiket yang sudah ditutup dibuka lagi: klien jelas masih butuh bantuan.
        $ticket->update(['status' => 'customer_reply', 'last_reply_at' => now(), 'closed_at' => null]);

        app(NotificationService::class)->ticketRepliedByClient($ticket, $reply);

        return 'ticket:' . $ticket->id;
    }

    private function appendToChat(ChatConversation $conversation, MimeMessage $mail, string $body): string
    {
        if ($conversation->status === 'closed') {
            $conversation->update(['status' => 'open', 'assigned_admin_id' => null, 'assigned_at' => null]);
        }

        $first = true;
        $messages = [];

        foreach ($mail->attachments as $att) {
            if (! $this->allowed($att['name'])) {
                continue;
            }

            $path = 'chat/' . Str::random(40) . '.' . $this->extension($att['name']);
            Storage::disk('local')->put($path, $att['content']);

            $messages[] = new ChatMessage([
                'sender' => 'user',
                'message' => $first ? Str::limit($body, 4000, '') : null,
                'attachment_path' => $path,
                'attachment_name' => Str::limit($att['name'], 200, ''),
                'attachment_mime' => $att['mime'],
            ]);

            $first = false;
        }

        if ($first) {
            $messages[] = new ChatMessage(['sender' => 'user', 'message' => Str::limit($body, 4000, '')]);
        }

        foreach ($messages as $message) {
            $conversation->messages()->save($message);
        }

        $conversation->increment('unread_for_admin', count($messages));
        $conversation->update(['last_message_at' => now()]);

        return 'chat:' . $conversation->id;
    }

    private function appendToMailThread(MimeMessage $mail, array $from, string $subject, string $body): string
    {
        $email = $from['email'];
        $thread = $this->resolveMailThread($mail, $email, $subject);
        $isNew = ! $thread;

        if ($isNew) {
            $client = Client::where('email', $email)->first();

            $thread = MailThread::create([
                'subject' => Str::limit(MailThread::normalizeSubject($subject), 250, ''),
                'contact_email' => $email,
                'contact_name' => $from['name'] ?: null,
                'client_id' => $client?->id,
                'status' => 'open',
                'last_message_at' => now(),
            ]);
        } elseif ($thread->status === 'closed') {
            $thread->update(['status' => 'open']);
        }

        $stored = [];

        foreach ($mail->attachments as $att) {
            if (! $this->allowed($att['name'])) {
                continue;
            }

            $path = 'mail/' . Str::random(40) . '.' . $this->extension($att['name']);
            Storage::disk('local')->put($path, $att['content']);

            $stored[] = [
                'path' => $path,
                'name' => Str::limit($att['name'], 200, ''),
                'mime' => $att['mime'],
                'size' => strlen($att['content']),
            ];
        }

        $thread->messages()->create([
            'direction' => 'in',
            'from_email' => $email,
            'from_name' => $from['name'] ?: null,
            'to_email' => (string) (Setting::get('imap_username') ?: config('mail.from.address')),
            'subject' => Str::limit($subject !== '' ? $subject : MailThread::NO_SUBJECT, 250, ''),
            'body' => Str::limit($body !== '' ? $body : '(lampiran email)', 60000, ''),
            'message_id' => Str::limit($mail->messageId(), 250, '') ?: null,
            'attachments' => $stored ?: null,
        ]);

        $thread->increment('unread_count');
        $thread->update(['last_message_at' => now()]);

        // Pengunjung membalas lewat email: ikut tampil di widget chat-nya.
        ChatMailMirror::toWidget($thread, 'user', $body !== '' ? $body : '(lampiran email)');

        // Email pertama dari pelanggan: robot membalas satu kali sebagai tanda
        // terima, sampai admin membalas sendiri.
        if ($isNew) {
            MailAutomation::autoReply($thread, $mail->messageId() ?: null);
        }

        if ($isNew) {
            ActivityLog::record(
                'ticket',
                'Email baru dari ' . $thread->display_name,
                Str::limit($subject !== '' ? $subject : $body, 80),
                route('admin.mail.show', $thread),
                'warning',
                $thread->client_id,
            );
        }

        return 'mail:' . $thread->id;
    }

    /**
     * Cari thread yang dilanjutkan email ini. Pengirim wajib sama dengan
     * lawan bicara thread, supaya orang lain tidak bisa menyusup ke thread
     * pelanggan hanya dengan menebak token atau Message-ID.
     */
    private function resolveMailThread(MimeMessage $mail, string $email, string $subject): ?MailThread
    {
        // 1) Token di subjek
        if (preg_match('/\[MAIL-(\d+)\]/i', $subject, $m)) {
            $thread = MailThread::find((int) $m[1]);

            if ($thread && strtolower($thread->contact_email) === $email) {
                return $thread;
            }
        }

        // 2) Header In-Reply-To / References menunjuk ke pesan yang sudah kita simpan
        $ids = $this->referencedMessageIds($mail);

        if ($ids !== []) {
            $known = MailMessage::with('thread')->whereIn('message_id', $ids)->latest('id')->first();

            if ($known?->thread && strtolower($known->thread->contact_email) === $email) {
                return $known->thread;
            }
        }

        // 3) Thread terbuka dari pengirim yang sama dengan subjek yang sama
        return MailThread::where('contact_email', $email)
            ->where('status', 'open')
            ->where('subject', MailThread::normalizeSubject($subject))
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, string> Message-ID (tanpa <>) dari In-Reply-To dan References
     */
    private function referencedMessageIds(MimeMessage $mail): array
    {
        $raw = $mail->header('in-reply-to') . ' ' . $mail->header('references');

        preg_match_all('/<([^<>\s]+)>/', $raw, $found);

        return array_values(array_unique($found[1] ?? []));
    }

    /**
     * Alamat milik aplikasi sendiri — email darinya diabaikan supaya
     * balasan kita tidak masuk lagi ke inbox dan memicu perulangan.
     */
    private function ownAddresses(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($v) => strtolower(trim((string) $v)),
            [
                config('mail.from.address'),
                Setting::get('mail_from_address'),
                Setting::get('mail_username'),
                Setting::get('imap_username'),
            ],
        ))));
    }

    private function extension(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private function allowed(string $name): bool
    {
        return in_array($this->extension($name), self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * Kunci unik email (Message-ID, atau sidik jari header bila tidak ada).
     */
    public static function dedupeKey(MimeMessage $mail, string $raw): string
    {
        $id = $mail->messageId();

        return sha1($id !== '' ? $id : $mail->header('from') . '|' . $mail->header('date') . '|' . $mail->subject() . '|' . strlen($raw));
    }

    public static function log(string $key, MimeMessage $mail, string $result): void
    {
        DB::table('inbound_emails')->insert([
            'dedupe_key' => $key,
            'message_id' => Str::limit($mail->messageId(), 250, '') ?: null,
            'from_email' => Str::limit($mail->from()['email'], 250, ''),
            'subject' => Str::limit($mail->subject(), 250, ''),
            'result' => Str::limit($result, 120, ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
