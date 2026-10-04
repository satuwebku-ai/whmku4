<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan ke klien setiap kali pengaturan keamanan akunnya berubah
 * (password, 2FA, email). Hanya email, supaya pemilik akun yang sebenarnya
 * tahu kalau ada orang lain yang mengubahnya.
 */
class ClientSecurityAlert extends Notification
{
    public function __construct(
        public string $event,
        public string $siteName,
        public ?string $ip = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Keamanan akun {$this->siteName}: {$this->event}")
            ->greeting('Halo,')
            ->line("Pengaturan keamanan akun {$this->siteName} Anda baru saja berubah: **{$this->event}**.")
            ->line('Waktu: ' . now()->format('d M Y H:i') . ($this->ip ? " · IP: {$this->ip}" : ''))
            ->line('Kalau ini memang Anda, tidak ada yang perlu dilakukan.')
            ->line('**Kalau BUKAN Anda**, segera ganti password dan hubungi tim support kami.');
    }
}
