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
        // Tahap "masih perlu bantuan?" sebelum ditutup: dikirim {jam_sisa} jam
        // SEBELUM batas tutup otomatis. Kalau dimatikan, thread langsung
        // ditutup di batas jam (perilaku lama).
        'mail_idle_prompt_enabled' => '1',
        'mail_idle_grace_hours' => '24',
        'mail_idle_prompt_body' => "Halo {nama},\n\nApakah masalah Anda sudah teratasi?\n\nKalau masih membutuhkan bantuan, cukup balas email ini dan kami akan melanjutkan. Kalau tidak ada balasan dalam {jam_sisa} jam, percakapan ini akan kami tutup otomatis.\n\nSalam,\n{site}",
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
     * Jeda antara email "masih perlu bantuan?" dan penutupan. Selalu lebih
     * kecil dari batas tutup supaya pertanyaan terkirim sebelum batas habis.
     */
    public static function graceHours(): int
    {
        return max(1, min((int) self::get('mail_idle_grace_hours'), self::closeHours() - 1));
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
            '{jam_sisa}' => (string) self::graceHours(),
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
     * Penutupan thread yang tidak dibalas pelanggan, dua tahap:
     *
     *  1. Pesan terakhir balasan ADMIN (bukan robot) dan pelanggan diam
     *     selama (batas tutup - jeda) jam -> kirim email "masih perlu bantuan?".
     *  2. Sesudah jeda itu pelanggan tetap tidak membalas -> kirim
     *     pemberitahuan dan tutup.
     *
     * Kalau tahap pertanyaan dimatikan (mail_idle_prompt_enabled=0), thread
     * langsung ditutup di batas jam, seperti sebelumnya.
     *
     * @return int jumlah thread yang DITUTUP
     */
    public static function closeStale(): int
    {
        if (! self::on('mail_idle_prompt_enabled')) {
            return self::closeLegacy();
        }

        self::promptStale();

        return self::closePrompted();
    }

    private static function promptStale(): void
    {
        $limit = now()->subHours(self::closeHours() - self::graceHours());

        MailThread::open()
            ->whereNull('idle_prompted_at')
            ->where('last_message_at', '<', $limit)
            ->with('latestMessage')
            ->chunkById(100, function ($threads) {
                foreach ($threads as $thread) {
                    $last = $thread->latestMessage;

                    // Bola di admin (pesan terakhir dari pelanggan) atau
                    // yang terakhir cuma balasan robot -> jangan ditanya.
                    if (! $last || $last->direction !== 'out' || $last->is_auto) {
                        continue;
                    }

                    $body = self::fill(self::get('mail_idle_prompt_body'), $thread);

                    try {
                        MailboxMailer::send($thread, 'Re: ' . $thread->subject, $body, [], null, null, true);
                    } catch (Throwable $e) {
                        report($e);

                        continue;
                    }

                    $thread->update(['idle_prompted_at' => now()]);
                    ChatMailMirror::toWidget($thread, 'bot', $body);
                }
            });
    }

    private static function closePrompted(): int
    {
        $closed = 0;

        MailThread::open()
            ->whereNotNull('idle_prompted_at')
            ->where('idle_prompted_at', '<', now()->subHours(self::graceHours()))
            ->chunkById(100, function ($threads) use (&$closed) {
                foreach ($threads as $thread) {
                    // Pelanggan sempat membalas sesudah ditanya -> batalkan.
                    $replied = $thread->messages()
                        ->where('direction', 'in')
                        ->where('created_at', '>=', $thread->idle_prompted_at)
                        ->exists();

                    if ($replied) {
                        $thread->update(['idle_prompted_at' => null]);

                        continue;
                    }

                    self::closeThread($thread);
                    $closed++;
                }
            });

        return $closed;
    }

    /**
     * Perilaku lama: tutup langsung kalau pesan terakhir balasan admin dan
     * sudah lewat batas jam.
     */
    private static function closeLegacy(): int
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

                    self::closeThread($thread);
                    $closed++;
                }
            });

        return $closed;
    }

    private static function closeThread(MailThread $thread): void
    {
        if (self::on('mail_autoclose_notice')) {
            $body = self::fill(self::get('mail_autoclose_body'), $thread);

            try {
                MailboxMailer::send($thread, 'Re: ' . $thread->subject, $body, [], null, null, true);
            } catch (Throwable $e) {
                report($e);
            }

            ChatMailMirror::toWidget($thread, 'bot', $body);
        }

        $thread->update(['status' => 'closed', 'idle_prompted_at' => null]);

        // toWidget() membuka kembali chat cerminan thread ini; tutup lagi
        // supaya tidak tertinggal "open" setelah thread-nya selesai.
        $thread->chatConversation?->update(['status' => 'closed']);
    }
}
