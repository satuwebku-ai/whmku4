<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceCreated extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function __construct(public Invoice $invoice) {}

    private function data(object $notifiable): array
    {
        return [
            'client_name' => $notifiable->name,
            'site_name' => Setting::get('site_name', config('app.name')),
            'invoice_number' => $this->invoice->invoice_number,
            'total' => 'Rp ' . number_format((float) $this->invoice->total, 0, ',', '.'),
            'due_date' => $this->invoice->due_date->format('d M Y'),
            'invoice_url' => route('client.invoices.show', $this->invoice),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('invoice_created');

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        // PDF dilampirkan kalau bisa dibuat. Kegagalan membuat PDF tidak
        // boleh membatalkan pengiriman email tagihannya.
        try {
            $this->invoice->loadMissing(['items', 'client', 'coupon']);

            $pdf = Pdf::loadView('client.invoices.pdf', ['invoice' => $this->invoice])->setPaper('a4');

            $mail->attachData(
                $pdf->output(),
                "Invoice-{$this->invoice->invoice_number}.pdf",
                ['mime' => 'application/pdf']
            );
        } catch (Throwable $e) {
            Log::warning('Gagal melampirkan PDF invoice: ' . $e->getMessage(), [
                'invoice_id' => $this->invoice->id,
            ]);
        }

        $this->applyPromoBanner($mail);

        return $mail->salutation("Salam,\n{$data['site_name']}");
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('invoice_created');

        return NotificationTemplate::substitute($tpl['body_whatsapp'], $this->data($notifiable));
    }

    public function toSms(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('invoice_created');

        return NotificationTemplate::substitute($tpl['body_sms'], $this->data($notifiable));
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->data($notifiable);

        return [
            'title' => "Invoice Baru — {$data['invoice_number']}",
            'body'  => "Tagihan {$data['total']} jatuh tempo {$data['due_date']}.",
            'url'   => $data['invoice_url'],
        ];
    }
}
