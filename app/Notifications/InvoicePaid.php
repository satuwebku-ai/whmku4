<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePaid extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function __construct(public Invoice $invoice) {}

    private function data(object $notifiable): array
    {
        return [
            'client_name' => $notifiable->name,
            'invoice_number' => $this->invoice->invoice_number,
            'total' => 'Rp ' . number_format((float) $this->invoice->total, 0, ',', '.'),
            'services_url' => route('client.services'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('invoice_paid');

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        $this->applyPromoBanner($mail);

        return $mail->salutation("Salam,\n{$site}");
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('invoice_paid');

        return NotificationTemplate::substitute($tpl['body_whatsapp'], $this->data($notifiable));
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->data($notifiable);

        return [
            'title' => 'Pembayaran Diterima',
            'body'  => "Invoice {$data['invoice_number']} ({$data['total']}) sudah lunas. Layanan aktif.",
            'url'   => $data['services_url'],
        ];
    }
}
