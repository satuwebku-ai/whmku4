<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceSuspended extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function __construct(
        public string $serviceLabel, // nama domain / layanan yang disuspend
        public Invoice $invoice,
    ) {}

    private function data(object $notifiable): array
    {
        return [
            'client_name' => $notifiable->name,
            'service_name' => $this->serviceLabel,
            'invoice_number' => $this->invoice->invoice_number,
            'total' => 'Rp ' . number_format((float) $this->invoice->total, 0, ',', '.'),
            'invoice_url' => route('client.invoices.show', $this->invoice),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('service_suspended');

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        return $mail->salutation("Salam,\n{$site}");
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('service_suspended');

        return NotificationTemplate::substitute($tpl['body_whatsapp'], $this->data($notifiable));
    }

    public function toSms(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('service_suspended');

        return NotificationTemplate::substitute($tpl['body_sms'], $this->data($notifiable));
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->data($notifiable);

        return [
            'title' => 'Layanan Disuspend',
            'body'  => "{$data['service_name']} disuspend — tagihan {$data['invoice_number']} belum dibayar.",
            'url'   => $data['invoice_url'],
        ];
    }
}
