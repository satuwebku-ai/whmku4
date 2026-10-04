<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kode OTP untuk tindakan sensitif di profil (ganti password, matikan 2FA).
 *
 * Berbeda dari notifikasi lain, kanal dipilih KLIEN saat meminta kode
 * (email atau WhatsApp) — bukan ditentukan preferensi notifikasi — karena
 * klien yang meminta kode jelas ingin menerimanya di kanal itu.
 */
class SecurityOtpCode extends Notification
{
    use UsesNotificationTemplate;

    public function __construct(
        public string $code,
        public string $action,
        public string $channel = 'email',
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channel === 'whatsapp' ? [WhatsAppChannel::class] : ['mail'];
    }

    private function data(object $notifiable): array
    {
        return [
            'client_name' => $notifiable->name,
            'site_name' => Setting::get('site_name', config('app.name')),
            'code' => $this->code,
            'action' => $this->action,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('client_security_otp');

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        return $mail->salutation('Terima kasih.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('client_security_otp');

        return NotificationTemplate::substitute((string) $tpl['body_whatsapp'], $this->data($notifiable));
    }
}
