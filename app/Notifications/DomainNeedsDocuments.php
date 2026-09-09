<?php

namespace App\Notifications;

use App\Models\Domain;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DomainNeedsDocuments extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function __construct(
        public Domain $domain,
        public string $tldExt,
    ) {}

    private function data(object $notifiable): array
    {
        return [
            'client_name' => $notifiable->name,
            'domain_name' => $this->domain->domain_name,
            'tld' => ".{$this->tldExt}",
            'upload_url' => route('client.domains.documents', $this->domain),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('domain_needs_documents');

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, NotificationTemplate::substitute($tpl['body_mail'], $data));

        return $mail->salutation("Salam,\n{$site}");
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('domain_needs_documents');

        return NotificationTemplate::substitute($tpl['body_whatsapp'], $this->data($notifiable));
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->data($notifiable);

        return [
            'title' => 'Dokumen Domain Diperlukan',
            'body'  => "Domain {$data['domain_name']} ({$data['tld']}) perlu dokumen tambahan sebelum aktif.",
            'url'   => $data['upload_url'],
        ];
    }
}
