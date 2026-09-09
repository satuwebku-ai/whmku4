<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke KLIEN saat staf membalas tiketnya (bukan catatan internal —
 * itu tidak pernah terlihat klien, jadi tidak masuk akal dinotifikasi).
 *
 * Sebelum fitur ini ada, balasan tiket sama sekali tidak memberi tahu
 * klien lewat email/WhatsApp -- mereka cuma tahu ada balasan kalau
 * kebetulan membuka halaman tiketnya lagi.
 */
class TicketReplied extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function __construct(
        public Ticket $ticket,
        public TicketReply $reply,
    ) {}

    private function data(): array
    {
        return [
            'client_name'    => $this->ticket->client->name ?? '',
            'site_name'      => config('app.name'),
            'ticket_number'  => $this->ticket->ticket_number,
            'subject'        => $this->ticket->subject,
            'staff_name'     => $this->reply->admin->name ?? 'Tim Support',
            'message'        => $this->reply->message,
            'ticket_url'     => route('client.tickets.show', $this->ticket),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->data();
        $tpl = NotificationTemplate::effective('ticket_replied');

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$data['client_name']},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        return $mail->salutation('Terima kasih.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('ticket_replied');

        return NotificationTemplate::substitute($tpl['body_whatsapp'], $this->data());
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->data();

        return [
            'title' => "Balasan Tiket {$data['ticket_number']}",
            'body'  => "{$data['staff_name']}: " . \Illuminate\Support\Str::limit($data['message'], 100),
            'url'   => $data['ticket_url'],
        ];
    }
}
