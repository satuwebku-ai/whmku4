<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Menerapkan pengaturan email dari database (Pengaturan → Email) ke
 * konfigurasi runtime Laravel, menimpa nilai MAIL_* di .env.
 *
 * Kalau host SMTP belum diisi di panel admin, tidak ada yang diubah dan
 * aplikasi tetap memakai .env seperti sebelumnya.
 */
class MailConfig
{
    /**
     * Ambil nilai SMTP dari database. Dipakai juga oleh tombol "Kirim Email Uji".
     */
    public static function saved(): array
    {
        return [
            'host'       => (string) Setting::get('mail_host', ''),
            'port'       => (int) Setting::get('mail_port', 465),
            'encryption' => (string) Setting::get('mail_encryption', 'ssl'),
            'username'   => (string) Setting::get('mail_username', ''),
            'password'   => (string) Setting::get('mail_password', ''),
            'from_address' => (string) Setting::get('mail_from_address', ''),
            'from_name'  => (string) Setting::get('mail_from_name', ''),
            'reply_to'   => (string) Setting::get('mail_reply_to', ''),
        ];
    }

    public static function apply(?array $values = null): void
    {
        try {
            $v = $values ?? static::saved();

            if (blank($v['host'] ?? null)) {
                return;
            }

            $port = (int) ($v['port'] ?: 465);

            // ssl  = TLS langsung (port 465)  -> scheme smtps
            // tls  = STARTTLS (port 587)      -> scheme smtp
            // none = tanpa enkripsi           -> scheme smtp
            $scheme = ($v['encryption'] ?? 'ssl') === 'ssl' ? 'smtps' : 'smtp';

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $v['host'],
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.scheme' => $scheme,
                'mail.mailers.smtp.username' => filled($v['username'] ?? null) ? $v['username'] : null,
                'mail.mailers.smtp.password' => filled($v['password'] ?? null) ? $v['password'] : null,
                'mail.mailers.smtp.timeout' => 20,
            ]);

            $from = filled($v['from_address'] ?? null) ? $v['from_address'] : ($v['username'] ?? null);

            if (filled($from) && filter_var($from, FILTER_VALIDATE_EMAIL)) {
                config(['mail.from.address' => $from]);
            }

            if (filled($v['from_name'] ?? null)) {
                config(['mail.from.name' => $v['from_name']]);
            }

            if (filled($v['reply_to'] ?? null) && filter_var($v['reply_to'], FILTER_VALIDATE_EMAIL)) {
                config(['mail.reply_to' => ['address' => $v['reply_to'], 'name' => config('mail.from.name')]]);
            }

            // Mailer yang sudah terlanjur dibuat (mis. di worker antrean) harus
            // dibangun ulang supaya memakai konfigurasi baru.
            Mail::purge('smtp');
            Mail::purge();
        } catch (Throwable) {
            // Database/cache belum siap (instalasi awal, migrate) -- abaikan.
        }
    }

    /**
     * Pengaturan IMAP (email masuk).
     */
    public static function imap(): array
    {
        return [
            'enabled'    => (string) Setting::get('imap_enabled', '0') === '1',
            'host'       => (string) Setting::get('imap_host', ''),
            'port'       => (int) Setting::get('imap_port', 993),
            'encryption' => (string) Setting::get('imap_encryption', 'ssl'),
            'username'   => (string) Setting::get('imap_username', ''),
            'password'   => (string) Setting::get('imap_password', ''),
            'folder'     => (string) (Setting::get('imap_folder') ?: 'INBOX'),
            'verify_cert' => (string) Setting::get('imap_verify_cert', '1') === '1',
        ];
    }
}
