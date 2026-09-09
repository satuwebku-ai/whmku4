<?php

namespace App\Notifications;

use App\Models\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Kirim dokumen persyaratan domain (KTP/NPWP/dll) ke registrar/reseller
 * lewat email — untuk registrar yang verifikasi dokumennya masih manual
 * lewat email, bukan API.
 *
 * Dikirim lewat rute "on-demand" (Notification::route('mail', $email)),
 * BUKAN ke model Client/Admin — penerimanya alamat bebas yang diketik
 * admin, bukan salah satu akun di sistem. Karena itu juga TIDAK
 * memakai ResolvesChannels/UsesNotificationTemplate seperti notifikasi
 * lain — ini email operasional B2B ke registrar, bukan komunikasi ke
 * klien, jadi kata-katanya tidak perlu bisa diedit admin lewat menu
 * Template Notifikasi.
 */
class DomainDocumentsForwarded extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, \App\Models\DomainDocument>  $documents
     */
    public function __construct(
        public Domain $domain,
        public Collection $documents,
        public ?string $note = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $client = $this->domain->client;

        $mail = (new MailMessage)
            ->subject('Dokumen Persyaratan Domain ' . $this->domain->domain_name)
            ->greeting('Halo,')
            ->line("Berikut dokumen persyaratan untuk pendaftaran domain **{$this->domain->domain_name}** dari klien kami.")
            ->line('Nama pemilik: ' . ($client->name ?? '—'))
            ->line('Email pemilik: ' . ($client->email ?? '—'));

        if (filled($this->note)) {
            $mail->line('Catatan: ' . $this->note);
        }

        $mail->line('Dokumen terlampir: ' . $this->documents->count() . ' berkas.');

        foreach ($this->documents as $document) {
            $path = Storage::disk('local')->path($document->file_path);

            // Jaga-jaga kalau berkas fisiknya sudah tidak ada di server
            // (mis. terhapus manual) -- daripada seluruh pengiriman
            // gagal cuma karena satu lampiran, lewati berkas itu saja.
            if (is_file($path)) {
                $mail->attach($path, ['as' => $document->original_name]);
            }
        }

        return $mail;
    }
}
