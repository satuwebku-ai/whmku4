<?php

namespace App\Services\Mail;

use App\Models\ActivityLog;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mengubah satu email masuk menjadi balasan tiket atau pesan Live Chat.
 *
 *  - Subjek memuat nomor tiket (TKT-2026-0001) DAN pengirim = email klien
 *    pemilik tiket  -> jadi balasan tiket tersebut.
 *  - Subjek memuat [CHAT-12] DAN pengirim = email percakapan itu -> masuk
 *    ke percakapan yang sama.
 *  - Selain itu -> percakapan Live Chat (channel email) baru, atau lanjut
 *    percakapan email terbuka dari alamat yang sama.
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

        // 2) Lanjutan percakapan chat berdasarkan token di subjek
        $conversation = null;

        if (preg_match('/\[CHAT-(\d+)\]/i', $subject, $m)) {
            $candidate = ChatConversation::find((int) $m[1]);

            if ($candidate && strtolower((string) $candidate->email) === $email) {
                $conversation = $candidate;
            }
        }

        // 3) Percakapan email terbuka dari alamat yang sama, atau baru
        $conversation ??= ChatConversation::where('channel', 'email')
            ->where('status', 'open')
            ->where('email', $email)
            ->latest('id')
            ->first();

        return $this->appendToChat($conversation, $mail, $from, $subject, $body);
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

    private function appendToChat(?ChatConversation $conversation, MimeMessage $mail, array $from, string $subject, string $body): string
    {
        $isNew = ! $conversation;

        if ($isNew) {
            $client = Client::where('email', $from['email'])->first();

            $conversation = ChatConversation::create([
                'client_id' => $client?->id,
                'name' => $client?->name ?? ($from['name'] ?: Str::before($from['email'], '@')),
                'email' => $from['email'],
                'phone' => $client?->phone,
                'channel' => 'email',
                'status' => 'open',
                'last_message_at' => now(),
                'page_url' => null,
            ]);
        } elseif ($conversation->status === 'closed') {
            $conversation->update(['status' => 'open', 'assigned_admin_id' => null, 'assigned_at' => null]);
        }

        $text = $body;

        if ($isNew && $subject !== '') {
            $text = 'Subjek: ' . $subject . "\n\n" . $body;
        }

        $first = true;
        $saved = 0;

        $messages = [];

        foreach ($mail->attachments as $att) {
            if (! $this->allowed($att['name'])) {
                continue;
            }

            $path = 'chat/' . Str::random(40) . '.' . $this->extension($att['name']);
            Storage::disk('local')->put($path, $att['content']);

            $messages[] = new ChatMessage([
                'sender' => 'user',
                'message' => $first ? Str::limit($text, 4000, '') : null,
                'attachment_path' => $path,
                'attachment_name' => Str::limit($att['name'], 200, ''),
                'attachment_mime' => $att['mime'],
            ]);

            $first = false;
        }

        if ($first) {
            $messages[] = new ChatMessage(['sender' => 'user', 'message' => Str::limit($text, 4000, '')]);
        }

        foreach ($messages as $message) {
            $conversation->messages()->save($message);
            $saved++;
        }

        $conversation->increment('unread_for_admin', $saved);
        $conversation->update(['last_message_at' => now()]);

        if ($isNew) {
            ActivityLog::record(
                'ticket',
                'Email baru dari ' . $conversation->display_name,
                Str::limit($subject !== '' ? $subject : $body, 80),
                route('admin.chats.show', $conversation),
                'warning',
                $conversation->client_id,
            );
        }

        return 'chat:' . $conversation->id;
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
