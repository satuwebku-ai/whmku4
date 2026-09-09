<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCode extends Notification
{
    use Queueable, UsesNotificationTemplate;

    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        // Sengaja HANYA email: tujuannya membuktikan alamat email itu
        // benar-benar milik pendaftar, jadi mengirim lewat WhatsApp
        // justru menggagalkan maksudnya.
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));
        $data = ['client_name' => $notifiable->name, 'site_name' => $site, 'code' => $this->code];
        $tpl = NotificationTemplate::effective('verify_email_code');

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        return $mail->salutation("Salam,\n{$site}");
    }
}
