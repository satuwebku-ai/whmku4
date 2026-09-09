<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan ke admin untuk kejadian penting: pesanan masuk,
 * pembayaran diterima, tiket baru, klien mendaftar, dan sebagainya.
 *
 * Dibuat satu kelas generik alih-alih satu kelas per kejadian, karena
 * isinya hanya berbeda di judul/rincian — memecahnya jadi belasan kelas
 * hanya menambah berkas tanpa menambah kemampuan.
 *
 * Bagian [RINCIAN] (daftar label:nilai) berbeda tiap kejadian, jadi
 * tetap disusun di kode — cuma sapaan/kalimat pembuka & subjeknya yang
 * bisa diedit admin lewat template 'admin_alert'.
 */
class AdminAlert extends Notification
{
    use Queueable, ResolvesChannels, UsesNotificationTemplate;

    /**
     * @param  array<string, string>  $details  baris rincian: label => nilai
     */
    public function __construct(
        public string $judul,
        public array $details = [],
        public ?string $tautan = null,
        public string $level = 'info',
    ) {}

    private function data(object $notifiable): array
    {
        return ['admin_name' => $notifiable->name, 'site_name' => Setting::get('site_name', config('app.name')), 'judul' => $this->judul];
    }

    private function rincianText(): string
    {
        $lines = [];

        foreach ($this->details as $label => $nilai) {
            $lines[] = "**{$label}:** {$nilai}";
        }

        return implode("\n", $lines);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('admin_alert');

        $body = NotificationTemplate::substitute($tpl['body_mail'], $data);
        $body = str_replace('[RINCIAN]', $this->rincianText(), $body);

        $mail = (new MailMessage)
            ->subject(NotificationTemplate::substitute($tpl['subject'], $data))
            ->greeting("Halo {$notifiable->name},");

        $this->applyTemplateBody($mail, $body);

        if ($this->tautan) {
            $mail->action('Buka di Admin Panel', $this->tautan);
        }

        return $mail->salutation("Notifikasi otomatis dari {$site}");
    }

    public function toWhatsApp(object $notifiable): string
    {
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('admin_alert');

        $pesan = NotificationTemplate::substitute($tpl['body_whatsapp'], $data);
        $pesan = str_replace('[RINCIAN]', $this->rincianText(), $pesan);

        if ($this->tautan) {
            $pesan .= "\n\n" . $this->tautan;
        }

        return $pesan;
    }

    /**
     * Beda dari toMail()/toWhatsApp(): SENGAJA tidak menyisipkan
     * rincianText() penuh -- SMS berbayar per segmen 160 karakter, dan
     * daftar rincian (mis. isi tiket, nominal pembayaran) bisa jadi
     * panjang. Cukup judul + tautan ke admin panel untuk baca detailnya.
     */
    public function toSms(object $notifiable): string
    {
        $data = $this->data($notifiable);
        $tpl = NotificationTemplate::effective('admin_alert');

        $pesan = NotificationTemplate::substitute($tpl['body_sms'], $data);

        if ($this->tautan) {
            $pesan .= ' ' . $this->tautan;
        }

        return $pesan;
    }

    /**
     * Ringkas rincian jadi satu baris "Label: nilai, Label: nilai" --
     * notifikasi OS cuma punya ruang 1-2 baris, beda dari email yang
     * bisa menampilkan [RINCIAN] penuh per baris.
     */
    public function toPush(object $notifiable): array
    {
        $ringkas = collect($this->details)
            ->map(fn ($nilai, $label) => "{$label}: {$nilai}")
            ->implode(', ');

        return [
            'title' => $this->judul,
            'body'  => $ringkas,
            'url'   => $this->tautan,
        ];
    }
}
