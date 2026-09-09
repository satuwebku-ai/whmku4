<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email/WA promosi. Satu-satunya notifikasi yang bisa ditolak klien —
 * karena itu ditandai isPromotional.
 *
 * Judul & isi promo diketik admin sendiri tiap kali kirim broadcast
 * (beda dari 12 notifikasi lain yang teksnya tetap) — bagian yang bisa
 * diedit lewat template 'promo_broadcast' cuma "bungkus tetapnya"
 * (sapaan, footer opt-out), lewat marker [ISI_PROMO].
 */
class PromoBroadcast extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    public function __construct(
        public string $judul,
        public string $isi,
        public ?string $tautan = null,
        public ?string $labelTautan = null,
    ) {
        // SENGAJA di constructor, bukan re-deklarasi properti seperti
        // `protected bool $isPromotional = true;` -- PHP fatal error
        // kalau properti trait dideklarasikan ulang di kelas dengan nilai
        // default yang beda (ResolvesChannels sudah mendeklarasikannya
        // sebagai false). Constructor adalah satu-satunya cara aman untuk
        // "menimpa" nilai bawaan trait ini.
        $this->isPromotional = true;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));
        $tpl = NotificationTemplate::effective('promo_broadcast');

        $body = NotificationTemplate::substitute($tpl['body_mail'], ['client_name' => $notifiable->name]);
        $body = str_replace('[ISI_PROMO]', trim($this->isi), $body);

        $mail = (new MailMessage)
            ->subject($this->judul)
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, $body);

        if ($this->tautan) {
            $mail->action($this->labelTautan ?: 'Selengkapnya', $this->tautan);
        }

        return $mail->salutation("Salam,\n{$site}");
    }

    public function toWhatsApp(object $notifiable): string
    {
        $tpl = NotificationTemplate::effective('promo_broadcast');

        $pesan = NotificationTemplate::substitute($tpl['body_whatsapp'], ['client_name' => $notifiable->name]);
        $pesan = str_replace('[ISI_PROMO]', "*{$this->judul}*\n\n" . trim($this->isi), $pesan);

        if ($this->tautan) {
            $pesan .= "\n\n" . $this->tautan;
        }

        return $pesan;
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => $this->judul,
            'body'  => \Illuminate\Support\Str::limit(trim($this->isi), 120),
            'url'   => $this->tautan,
        ];
    }
}
