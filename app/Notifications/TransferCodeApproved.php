<?php

namespace App\Notifications;

use App\Models\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SENGAJA tidak memakai NotificationTemplate (sistem template yang bisa
 * diedit admin) seperti notifikasi lain -- ini mengirim kode EPP yang
 * setara password sekali pakai, jadi isinya dikunci di kode supaya
 * tidak berisiko rusak/hilang kalau admin tidak sengaja mengubah
 * template dengan cara yang menghapus placeholder kodenya.
 */
class TransferCodeApproved extends Notification
{
    use Queueable, ResolvesChannels;

    public function __construct(private Domain $domain, private string $code) {}

    public function via(object $notifiable): array
    {
        return $this->wantsEmail($notifiable) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Kode Transfer Domain {$this->domain->domain_name} Disetujui")
            ->line("Permintaan kode transfer (EPP/Auth Code) untuk domain **{$this->domain->domain_name}** sudah disetujui tim kami.")
            ->line('Kode transfer Anda:')
            ->line("**{$this->code}**")
            ->line('Kode ini setara password sekali pakai — jangan bagikan ke siapa pun kecuali Anda memang sedang memindahkan domain ke registrar lain.')
            ->line('Kode biasanya berlaku beberapa hari saja. Segera gunakan sebelum kedaluwarsa.');
    }
}
