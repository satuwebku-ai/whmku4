<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Terpisah dari SendOtpCode (khusus admin) karena template bawaannya
 * secara eksplisit menyebut "admin panel" di teksnya -- tidak cocok
 * dipakai ulang untuk login klien. Logika & struktur sama persis,
 * cuma template key & kata-kata defaultnya yang beda.
 */
class SendClientOtpCode extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function __construct(public string $code) {}

    private function data(object $notifiable): array
    {
        return ['client_name' => $notifiable->name, 'site_name' => config('app.name'), 'code' => $this->code];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('send_client_otp_code');

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        return $mail->salutation('Terima kasih.');
    }

    /**
     * Sama seperti notifikasi lain, SMS di sini masih tunduk pada
     * toggle "Terima notifikasi lewat SMS" di profil klien (lihat
     * ResolvesChannels::wantsSms()) -- kode OTP tetap terkirim lewat
     * email kalau klien mematikan SMS, jadi ini murni kanal tambahan,
     * bukan satu-satunya jalan menerima kode.
     */
    public function toSms(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('send_client_otp_code');

        return NotificationTemplate::substitute($tpl['body_sms'], $this->data($notifiable));
    }
}
