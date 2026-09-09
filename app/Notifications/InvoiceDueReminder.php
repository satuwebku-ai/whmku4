<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengingat tagihan. Dipakai untuk dua situasi:
 *  - sebelum jatuh tempo (H-n) -> template 'invoice_reminder_upcoming'
 *  - setelah lewat jatuh tempo -> template 'invoice_reminder_overdue'
 *
 * Dua template terpisah (bukan satu dengan percabangan di dalamnya)
 * supaya admin bisa atur nada bicara masing-masing situasi secara
 * independen — pengingat pertama semestinya tidak terasa menuduh.
 */
class InvoiceDueReminder extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function __construct(
        public Invoice $invoice,
        public int $daysLeft, // negatif = sudah lewat jatuh tempo
    ) {}

    private function templateKey(): string
    {
        return $this->daysLeft < 0 ? 'invoice_reminder_overdue' : 'invoice_reminder_upcoming';
    }

    private function data(object $notifiable): array
    {
        return [
            'client_name' => $notifiable->name,
            'invoice_number' => $this->invoice->invoice_number,
            'total' => 'Rp ' . number_format((float) $this->invoice->total, 0, ',', '.'),
            'due_date' => $this->invoice->due_date->format('d M Y'),
            'days_left' => $this->daysLeft,
            'days_late' => abs($this->daysLeft),
            'invoice_url' => route('client.invoices.show', $this->invoice),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective($this->templateKey());

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        return $mail->salutation("Salam,\n{$site}");
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective($this->templateKey());

        return NotificationTemplate::substitute($tpl['body_whatsapp'], $this->data($notifiable));
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->data($notifiable);
        $overdue = $this->daysLeft < 0;

        return [
            'title' => $overdue ? 'Tagihan Terlambat' : 'Pengingat Jatuh Tempo',
            'body'  => $overdue
                ? "Invoice {$data['invoice_number']} ({$data['total']}) sudah terlambat {$data['days_late']} hari."
                : "Invoice {$data['invoice_number']} ({$data['total']}) jatuh tempo {$data['days_left']} hari lagi.",
            'url'   => $data['invoice_url'],
        ];
    }
}
