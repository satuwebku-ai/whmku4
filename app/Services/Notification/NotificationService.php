<?php

namespace App\Services\Notification;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Notifications\AdminAlert;
use App\Notifications\ClientWelcome;
use App\Notifications\InvoiceCreated;
use App\Notifications\InvoicePaid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Satu pintu untuk semua pengiriman notifikasi.
 *
 * Alasan dipusatkan di sini:
 *  1. Setiap kejadian perlu diperiksa dulu apakah notifikasinya diaktifkan
 *     admin. Kalau pengecekan itu tersebar di controller, mudah terlewat.
 *  2. Kegagalan mengirim notifikasi TIDAK BOLEH membatalkan transaksi.
 *     Kalau SMTP mati, pesanan tetap harus tersimpan. Semua pengiriman
 *     dibungkus try/catch di sini.
 *  3. Setiap kejadian sekalian dicatat ke log aktivitas admin.
 */
class NotificationService
{
    /**
     * Klien baru mendaftar.
     */
    public function clientRegistered(Client $client): void
    {
        if ($this->enabled('notify_welcome')) {
            $this->send($client, new ClientWelcome());
        }

        ActivityLog::record(
            'client',
            'Klien baru mendaftar',
            $client->name . ' (' . $client->email . ')',
            route('admin.clients.details', $client),
            'success',
            $client->id,
        );

        $this->alertAdmins('notify_admin_client', 'Klien baru mendaftar', [
            'Nama' => $client->name,
            'Email' => $client->email,
        ], route('admin.clients.details', $client));
    }

    /**
     * Invoice baru diterbitkan.
     */
    public function invoiceCreated(Invoice $invoice): void
    {
        $client = $invoice->client;

        if ($client && $this->enabled('notify_invoice')) {
            $this->send($client, new InvoiceCreated($invoice));
        }

        ActivityLog::record(
            'invoice',
            'Invoice baru: ' . $invoice->invoice_number,
            ($client->name ?? '—') . ' — Rp ' . number_format((float) $invoice->total, 0, ',', '.'),
            route('admin.invoices.details', $invoice),
            'info',
            $invoice->client_id,
        );

        $this->alertAdmins('notify_admin_order', 'Pesanan baru masuk', [
            'Invoice' => $invoice->invoice_number,
            'Klien' => $client->name ?? '—',
            'Total' => 'Rp ' . number_format((float) $invoice->total, 0, ',', '.'),
        ], route('admin.invoices.details', $invoice));
    }

    /**
     * Pembayaran diterima dan invoice lunas.
     */
    public function invoicePaid(Invoice $invoice): void
    {
        $client = $invoice->client;

        // Invoice isi ulang saldo dapat notifikasi khusus sendiri (lihat
        // balanceTopupPaid) yang menyebutkan saldo barunya — mengirim
        // notifikasi generik "Invoice dibayar" juga di sini akan jadi
        // dua email untuk satu kejadian yang sama.
        if ($client && $this->enabled('notify_paid') && ! $invoice->is_topup) {
            $this->send($client, new InvoicePaid($invoice));
        }

        ActivityLog::record(
            'payment',
            'Pembayaran diterima: ' . $invoice->invoice_number,
            ($client->name ?? '—') . ' — Rp ' . number_format((float) $invoice->total, 0, ',', '.'),
            route('admin.invoices.details', $invoice),
            'success',
            $invoice->client_id,
        );

        $this->alertAdmins('notify_admin_payment', 'Pembayaran diterima', [
            'Invoice' => $invoice->invoice_number,
            'Klien' => $client->name ?? '—',
            'Total' => 'Rp ' . number_format((float) $invoice->total, 0, ',', '.'),
        ], route('admin.invoices.details', $invoice), 'success');
    }

    /**
     * Konfirmasi isi ulang saldo — beda dari invoicePaid() biasa karena
     * menyebutkan nominal yang masuk DAN saldo terbaru, bukan sekadar
     * "invoice Anda telah dibayar".
     */
    public function balanceTopupPaid(\App\Models\Client $client, float $amount): void
    {
        if ($this->enabled('notify_paid')) {
            $this->send($client, new \App\Notifications\BalanceTopupPaid($amount, (float) $client->balance));
        }
    }

    /**
     * Klien mengunggah bukti transfer manual — perlu diverifikasi admin.
     * Berbeda dari invoicePaid(): di sini invoice BELUM lunas, baru bukti
     * transfernya yang masuk dan menunggu diperiksa.
     */
    public function paymentProofUploaded(\App\Models\Payment $payment): void
    {
        $this->alertAdmins('notify_admin_payment', 'Bukti transfer perlu diverifikasi', [
            'Referensi' => $payment->reference,
            'Klien' => $payment->client->name ?? '—',
            'Total' => 'Rp ' . number_format((float) $payment->total, 0, ',', '.'),
        ], route('admin.payments.details', $payment), 'warning');
    }

    /**
     * Domain untuk TLD yang mewajibkan data kelayakan (.us, .asia, dll)
     * berhenti sejenak menunggu admin mengisi datanya sebelum bisa
     * didaftarkan — lihat LiquidService::ELIGIBILITY_REQUIRED_TLDS.
     */
    /**
     * Klien sudah bayar ID Protection tapi aktivasi di registrar gagal —
     * uangnya sudah masuk, jadi ini WAJIB ditindaklanjuti admin manual,
     * tidak boleh didiamkan.
     */
    public function privacyActivationFailed(\App\Models\Domain $domain, string $reason): void
    {
        $this->alertAdmins('notify_admin_payment', 'ID Protection sudah dibayar tapi GAGAL diaktifkan', [
            'Domain' => $domain->domain_name,
            'Klien' => $domain->client->name ?? '—',
            'Alasan' => $reason,
        ], route('admin.domains.details', $domain), 'warning');
    }

    public function domainNeedsEligibility(\App\Models\Domain $domain, string $tldExt): void
    {
        $this->alertAdmins('notify_admin_payment', "Domain .{$tldExt} butuh data kelayakan tambahan", [
            'Domain' => $domain->domain_name,
            'Klien' => $domain->client->name ?? '—',
            'TLD' => ".{$tldExt}",
        ], route('admin.domains.details', $domain), 'warning');
    }

    /**
     * TLD Indonesia (.co.id, .ac.id, dst) — klien perlu diberi tahu
     * untuk upload dokumen (beda dari eligibility di atas yang murni
     * urusan admin, tidak melibatkan klien sama sekali).
     */
    public function domainNeedsDocuments(\App\Models\Domain $domain, string $tldExt): void
    {
        $client = $domain->client;

        if ($client && $this->enabled('notify_paid')) {
            $this->send($client, new \App\Notifications\DomainNeedsDocuments($domain, $tldExt));
        }

        $this->alertAdmins('notify_admin_payment', "Domain .{$tldExt} menunggu dokumen klien", [
            'Domain' => $domain->domain_name,
            'Klien' => $client->name ?? '—',
            'TLD' => ".{$tldExt}",
        ], route('admin.domains.details', $domain), 'warning');
    }

    /**
     * Tiket support baru dari klien.
     */
    public function ticketCreated($ticket): void
    {
        ActivityLog::record(
            'ticket',
            'Tiket baru: ' . $ticket->subject,
            ($ticket->client->name ?? '—') . ' — prioritas ' . $ticket->priority,
            route('admin.tickets.details', $ticket),
            $ticket->priority === 'urgent' ? 'danger' : 'warning',
            $ticket->client_id,
        );

        $this->alertAdmins('notify_admin_ticket', 'Tiket support baru', [
            'Nomor' => $ticket->ticket_number,
            'Subjek' => $ticket->subject,
            'Klien' => $ticket->client->name ?? '—',
            'Prioritas' => ucfirst($ticket->priority),
        ], route('admin.tickets.details', $ticket), $ticket->priority === 'urgent' ? 'danger' : 'warning');
    }

    /**
     * Staf membalas tiket -- beritahu klien lewat email (+WhatsApp kalau
     * aktif). Cuma dipanggil untuk balasan BIASA, bukan catatan internal
     * (itu tidak pernah terlihat klien, jadi tidak masuk akal
     * dinotifikasi ke mereka).
     */
    public function ticketRepliedByAdmin(\App\Models\Ticket $ticket, \App\Models\TicketReply $reply): void
    {
        if ($ticket->client && $this->enabled('notify_ticket_reply')) {
            $this->send($ticket->client, new \App\Notifications\TicketReplied($ticket, $reply));
        }
    }

    /**
     * Klien membalas tiket -- beritahu admin, sama seperti tiket baru.
     * Sebelumnya cuma tiket BARU yang memicu alert; balasan susulan dari
     * klien lewat tanpa pemberitahuan apa pun ke staf.
     */
    public function ticketRepliedByClient(\App\Models\Ticket $ticket, \App\Models\TicketReply $reply): void
    {
        ActivityLog::record(
            'ticket',
            'Klien membalas tiket: ' . $ticket->subject,
            ($ticket->client->name ?? '—') . ' — ' . $ticket->ticket_number,
            route('admin.tickets.details', $ticket),
            'warning',
            $ticket->client_id,
        );

        $this->alertAdmins('notify_admin_ticket', 'Klien membalas tiket', [
            'Nomor' => $ticket->ticket_number,
            'Subjek' => $ticket->subject,
            'Klien' => $ticket->client->name ?? '—',
        ], route('admin.tickets.details', $ticket));
    }

    /**
     * Kejadian umum yang cukup dicatat tanpa mengirim email.
     */
    public function log(string $type, string $title, ?string $description = null, ?string $link = null, string $level = 'info'): void
    {
        ActivityLog::record($type, $title, $description, $link, $level);
    }

    /**
     * Kirim peringatan ke semua admin aktif.
     */
    private function alertAdmins(string $settingKey, string $judul, array $details, ?string $link = null, string $level = 'info'): void
    {
        if (! $this->enabled($settingKey)) {
            return;
        }

        foreach ($this->admins() as $admin) {
            $this->send($admin, new AdminAlert($judul, $details, $link, $level));
        }
    }

    /**
     * @return Collection<int, Admin>
     */
    private function admins(): Collection
    {
        return Admin::where('is_active', true)->get();
    }

    /**
     * Apakah jenis notifikasi ini diaktifkan? Default menyala, supaya
     * pemasangan baru langsung berfungsi tanpa perlu setel apa-apa.
     */
    private function enabled(string $key): bool
    {
        return Setting::get($key, '1') === '1';
    }

    /**
     * Bungkus pengiriman supaya kegagalan notifikasi tidak pernah
     * menggagalkan proses bisnis yang memanggilnya.
     */
    private function send(object $notifiable, $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Notifikasi gagal dikirim: ' . $e->getMessage(), [
                'penerima' => $notifiable->email ?? '—',
                'jenis' => $notification::class,
            ]);
        }
    }
}
