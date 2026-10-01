<?php

namespace App\Services\Mail;

use App\Models\Admin;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim email dari Inbox Email admin (balasan maupun email baru) dan
 * mencatatnya sebagai pesan keluar di thread.
 *
 * Header Message-ID / In-Reply-To / References diisi supaya aplikasi email
 * pelanggan menampilkan percakapan sebagai satu thread, dan token [MAIL-id]
 * di subjek menjadi cadangan kalau header itu dibuang.
 */
class MailboxMailer
{
    /**
     * @param  array<int, UploadedFile>  $files
     *
     * @throws Throwable kalau pengiriman gagal (thread tidak diubah)
     */
    public static function send(
        MailThread $thread,
        string $subject,
        string $body,
        array $files = [],
        ?Admin $admin = null,
        ?string $inReplyTo = null,
    ): MailMessage {
        $site = (string) Setting::get('site_name', config('app.name'));
        $fromAddress = (string) (Setting::get('mail_from_address') ?: config('mail.from.address'));
        $fromName = (string) (Setting::get('mail_from_name') ?: $site);
        $domain = Str::after($fromAddress, '@') ?: 'localhost';

        $subject = trim(preg_replace('/\s*\[MAIL-\d+\]\s*/i', ' ', $subject) ?? $subject);
        $wireSubject = Str::limit($subject, 230, '') . ' ' . $thread->token();

        $text = rtrim($body) . "\n\n--\n" . ($admin?->name ? $admin->name . "\n" : '') . $site;

        $messageId = Str::uuid()->toString() . '@' . $domain;
        $references = $thread->messages()
            ->whereNotNull('message_id')
            ->orderByDesc('id')
            ->limit(10)
            ->pluck('message_id')
            ->reverse()
            ->values()
            ->all();

        $stored = self::storeFiles($files);

        try {
            Mail::raw($text, function ($mail) use ($thread, $wireSubject, $messageId, $inReplyTo, $references, $stored) {
                $mail->to($thread->contact_email, $thread->contact_name ?: null)->subject($wireSubject);

                $headers = $mail->getSymfonyMessage()->getHeaders();
                $headers->addIdHeader('Message-ID', $messageId);

                // Id dari pengirim lain bisa saja tidak valid secara RFC;
                // header threading hanya pelengkap, jangan sampai menggagalkan kirim.
                try {
                    if ($inReplyTo) {
                        $headers->addIdHeader('In-Reply-To', $inReplyTo);
                    }
                    if ($references !== []) {
                        $headers->addIdHeader('References', $references);
                    }
                } catch (Throwable $e) {
                    report($e);
                }

                foreach ($stored as $file) {
                    $mail->attachData(
                        Storage::disk('local')->get($file['path']),
                        $file['name'],
                        ['mime' => $file['mime']],
                    );
                }
            });
        } catch (Throwable $e) {
            foreach ($stored as $file) {
                Storage::disk('local')->delete($file['path']);
            }

            throw $e;
        }

        $message = $thread->messages()->create([
            'direction' => 'out',
            'from_email' => $fromAddress,
            'from_name' => $fromName,
            'to_email' => $thread->contact_email,
            'subject' => Str::limit($subject, 250, ''),
            'body' => $body,
            'message_id' => $messageId,
            'admin_id' => $admin?->id,
            'attachments' => $stored ?: null,
        ]);

        $thread->update(['status' => 'open', 'last_message_at' => now()]);

        return $message;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{path:string, name:string, mime:string, size:int}>
     */
    private static function storeFiles(array $files): array
    {
        $stored = [];

        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $path = 'mail/' . Str::random(40) . '.' . $ext;

            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            $stored[] = [
                'path' => $path,
                'name' => Str::limit($file->getClientOriginalName(), 200, ''),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $file->getSize(),
            ];
        }

        return $stored;
    }
}
