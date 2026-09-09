<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientWelcome extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));
        $tpl = NotificationTemplate::effective('client_welcome');
        $data = ['client_name' => $notifiable->name, 'site_name' => $site, 'dashboard_url' => route('client.dashboard')];

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        $this->applyPromoBanner($mail);

        return $mail->salutation("Salam,\n{$site}");
    }

    public function toWhatsApp(object $notifiable): string
    {
        $site = Setting::get('site_name', config('app.name'));
        $tpl = NotificationTemplate::effective('client_welcome');
        $data = ['client_name' => $notifiable->name, 'site_name' => $site, 'dashboard_url' => route('client.dashboard')];

        return NotificationTemplate::substitute($tpl['body_whatsapp'], $data);
    }

    public function toPush(object $notifiable): array
    {
        $site = Setting::get('site_name', config('app.name'));

        return [
            'title' => "Selamat Datang di {$site}",
            'body'  => "Akun Anda sudah aktif, {$notifiable->name}. Yuk mulai jelajahi dashboard Anda.",
            'url'   => route('client.dashboard'),
        ];
    }
}
