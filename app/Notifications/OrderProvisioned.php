<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bagian [DAFTAR_LAYANAN] (kredensial hosting + status domain) berbeda
 * tiap pesanan, jadi tetap disusun di kode — cuma kalimat pembuka/
 * penutup yang bisa diedit admin lewat template 'order_provisioned'.
 * Sengaja HANYA email (tidak ada versi WhatsApp) karena memuat password.
 */
class OrderProvisioned extends Notification
{
    use Queueable, UsesNotificationTemplate;

    /**
     * @param  array<int, array{domain: string, username: string, password: string}>  $hostingCredentials
     * @param  array<int, array{domain: string, success: bool, message: string}>  $domainResults
     */
    public function __construct(
        public array $hostingCredentials,
        public array $domainResults,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    private function daftarLayananText(): string
    {
        $lines = [];

        foreach ($this->hostingCredentials as $cred) {
            $lines[] = "**Hosting — {$cred['domain']}**";
            $lines[] = "Username cPanel: `{$cred['username']}`";
            $lines[] = "Password: `{$cred['password']}`";
            $lines[] = '⚠️ Segera login dan ganti password ini. Kami tidak menyimpan password Anda, jadi simpan email ini sampai Anda menggantinya.';
        }

        foreach ($this->domainResults as $d) {
            $lines[] = $d['success']
                ? "**Domain {$d['domain']}** berhasil didaftarkan."
                : "**Domain {$d['domain']}** belum berhasil diproses otomatis: {$d['message']} Tim kami akan menindaklanjuti secara manual.";
        }

        return implode("\n", $lines);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = ['client_name' => $notifiable->name, 'services_url' => route('client.services')];
        $tpl = NotificationTemplate::effective('order_provisioned');

        $body = NotificationTemplate::substitute($tpl['body_mail'], $data);
        $body = str_replace('[DAFTAR_LAYANAN]', $this->daftarLayananText(), $body);

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        return $this->applyTemplateBody($mail, $body);
    }
}
