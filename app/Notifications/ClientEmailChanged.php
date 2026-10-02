<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Peringatan keamanan ke alamat email LAMA saat email akun diganti.
 *
 * Dikirim lewat rute on-demand ke alamat lama (akun sudah tidak
 * memakainya), supaya pemilik asli tahu kalau akunnya diambil alih.
 * Sengaja tidak menyertakan nama klien di isi email: nama berasal dari
 * input klien dan email ini memakai markdown.
 */
class ClientEmailChanged extends Notification
{
    public function __construct(
        public string $maskedNewEmail,
        public string $siteName,
        public ?string $ip = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Email akun {$this->siteName} Anda diganti")
            ->greeting('Halo,')
            ->line("Alamat email akun {$this->siteName} yang tadinya memakai alamat ini baru saja diganti menjadi **{$this->maskedNewEmail}**.")
            ->line('Waktu: ' . now()->format('d M Y H:i') . ($this->ip ? " · IP: {$this->ip}" : ''))
            ->line('Kalau ini memang Anda, tidak ada yang perlu dilakukan.')
            ->line('**Kalau BUKAN Anda**, segera hubungi tim support kami agar akun diamankan. Jangan menunggu: email akun dipakai untuk reset password.');

        return $mail;
    }
}
