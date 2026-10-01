<?php

namespace App\Services\Mail;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Setting;
use Throwable;

/**
 * Otomatisasi Inbox Email:
 *  - balasan robot (tanda terima) pada email pertama pelanggan, satu kali
 *    per thread, sampai admin membalas sendiri;
 *  - penutupan otomatis thread yang sudah dibalas admin tapi tidak dibalas
 *    pelanggan (lihat perintah lumora:close-inactive-mail).
 */
class MailAutomation
{
    public const DEFAULTS = [
        'mail_autoreply_enabled' => '1',
        'mail_autoreply_body' => "Halo {nama},\n\nTerima kasih telah menghubungi {site}. Email Anda sudah kami terima dengan nomor referensi {ref}.\n\nTim kami akan membalas secepatnya{jam_kerja}. Anda tidak perlu mengirim ulang email yang sama; cukup balas email ini kalau ada tambahan informasi.\n\nEmail ini dikirim otomatis oleh sistem.",
        'mail_autoclose_enabled' => '1',
        'mail_autoclose_hours' => '72',
        'mail_autoclose_notice' => '1',
        'mail_autoclose_body' => "Halo {nama},\n\nKarena belum ada balasan dari Anda dalam {jam} jam terakhir, percakapan ini kami anggap selesai dan ditutup otomatis.\n\nKalau masih membutuhkan bantuan, cukup balas email ini kapan saja dan percakapan akan dibuka kembali.\n\nSalam,\n{site}",
    ];

    public static function get(string $key): string
    {
        $value = Setting::get($key, null);

        return ($value === null || $value === '') ? self::DEFAULTS[$key] : (string) $value;
    }

    public static function on(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function closeHours(): int
    {
        return max(1, (int) self::get('mail_autoclose_hours'));
    }

    /**
     * Ganti penanda {nama} {site} {ref} {jam} {jam_kerja} di teks template.
     */
    public static function fill(string $text, MailThread $thread): string
    {
        $hours = trim((string) Setting::get('support_hours', ''));

        return strtr($text, [
            '{nama}' => $thread->display_name,
            '{site}' => (string) Setting::get('site_name', config('app.name')),
            '{ref}' => trim($thread->token(), '[]'),
            '{jam}' => (string) self::closeHours(),
            '{jam_kerja}' => $hours !== '' ? ' (jam layanan: ' . $hours . ')' : '',
        ]);
    }

    /**
     * Tanda terima otomatis. Dikirim sekali per thread dan paling banyak
     * sekali per jam ke alamat yang sama (pencegah banjir/perulangan).
     */
    public static function autoReply(MailThread $thread, ?string $inReplyTo = null): void
    {
        if (! self::on('mail_autoreply_enabled') || $thread->chat_conversation_id) {
            return;
        }

        if ($thread->messages()->where('is_auto', true)->exists()) {
            return;
        }

        $recent = MailMessage::where('is_auto', true)
            ->where('to_email', $thread->contact_email)
            ->where('created_at', '>', now()->subHour())
            ->exists();

        if ($recent) {
            return;
        }

        try {
            MailboxMailer::send(
                $thread,
                'Re: ' . $thread->subject,
                self::fill(self::get('mail_autoreply_body'), $thread),
                [],
                null,
                $inReplyTo,
                true,
            );
        } catch (Throwable $e) {
            // Gagal kirim tanda terima tidak boleh menggagalkan email masuk.
            report($e);
        }
    }

    /**
     * Thread terbuka yang pesan terakhirnya balasan ADMIN (bukan robot) dan
     * sudah lebih lama dari batas jam -> pelanggan tidak membalas.
     */
    public static function closeStale(): int
    {
        $closed = 0;

        MailThread::open()
            ->where('last_message_at', '<', now()->subHours(self::closeHours()))
            ->with('latestMessage')
            ->chunkById(100, function ($threads) use (&$closed) {
                foreach ($threads as $thread) {
                    $last = $thread->latestMessage;

                    if (! $last || $last->direction !== 'out' || $last->is_auto) {
                        continue;
                    }

                    if (self::on('mail_autoclose_notice')) {
                        $body = self::fill(self::get('mail_autoclose_body'), $thread);

                        try {
                            MailboxMailer::send($thread, 'Re: ' . $thread->subject, $body, [], null, null, true);
                        } catch (Throwable $e) {
                            report($e);
                        }

                        ChatMailMirror::toWidget($thread, 'bot', $body);
                    }

                    $thread->update(['status' => 'closed']);
                    $closed++;
                }
            });

        return $closed;
    }
}
