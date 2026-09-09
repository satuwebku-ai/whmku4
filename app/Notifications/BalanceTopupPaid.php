<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BalanceTopupPaid extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function __construct(
        public float $amount,
        public float $newBalance,
    ) {}

    private function data(object $notifiable): array
    {
        return [
            'client_name' => $notifiable->name,
            'amount' => 'Rp ' . number_format($this->amount, 0, ',', '.'),
            'new_balance' => 'Rp ' . number_format($this->newBalance, 0, ',', '.'),
            'balance_url' => route('client.balance'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('balance_topup_paid');

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        return $mail->salutation("Salam,\n{$site}");
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('balance_topup_paid');

        return NotificationTemplate::substitute($tpl['body_whatsapp'], $this->data($notifiable));
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->data($notifiable);

        return [
            'title' => 'Saldo Bertambah',
            'body'  => "Top up {$data['amount']} berhasil. Saldo sekarang: {$data['new_balance']}.",
            'url'   => $data['balance_url'],
        ];
    }
}
