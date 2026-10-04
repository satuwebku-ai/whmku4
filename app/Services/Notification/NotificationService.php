<?php

namespace App\Services\Notification;

use App\Jobs\Notification\DeliverNotification;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\Setting;
use App\Notifications\AdminAlert;
use App\Notifications\ClientWelcome;
use App\Notifications\InvoiceCreated;
use App\Notifications\InvoiceDueReminder;
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
            $this->send($client, new ClientWelcome(), 'client:registered:' . $client->id);
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
            $this->send($client, new InvoiceCreated($invoice), 'invoice:created:' . $invoice->id);
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
     * Pengingat memakai event key stabil per invoice, tanggal jatuh tempo,
     * dan tahap agar retry cron tidak membuat pengiriman baru untuk tahap sama.
     */
    /** @return 'queued'|'already_handled'|'failed' */
    public function invoiceReminder(Invoice $invoice, int $daysLeft, string $stageKey): string
    {
        $client = $invoice->client;
        $dueDate = $invoice->due_date?->toDateString();

        if (! $client || ! $dueDate || ! $this->enabled('notify_reminder')) {
            return 'failed';
        }

        $eventKey = "invoice:reminder:{$invoice->id}:{$dueDate}:{$stageKey}";
        $duplicate = false;

        $queued = $this->send(
            $client,
            new InvoiceDueReminder($invoice, $daysLeft),
            $eventKey,
            $duplicate,
        );

        return $queued ? 'queued' : ($duplicate ? 'already_handled' : 'failed');
    }

    /**
     * Pembayaran diterima dan invoice lunas.
     */
    public function invoicePaid(Invoice $invoice): void
    {
        $client = $invoice->client;

        // Invoice isi ulang saldo dapat notifikasi khusus sendiri (lihat
        // balanceTopupPaid) — notifikasi generik akan menghasilkan pesan ganda.
        if ($client && $this->enabled('notify_paid') && ! $invoice->is_topup) {
            $this->send($client, new InvoicePaid($invoice), 'invoice:paid:' . $invoice->id);
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
     * menyebutkan nominal yang masuk dan saldo terbaru.
     */
    public function balanceTopupPaid(Client $client, float $amount): void
    {
        if ($this->enabled('notify_paid')) {
            $this->send($client, new \App\Notifications\BalanceTopupPaid($amount, (float) $client->balance), null);
        }
    }

    /**
     * Klien mengunggah bukti transfer manual — perlu diverifikasi admin.
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
     * Klien sudah bayar ID Protection tapi aktivasi di registrar gagal.
     */
    public function privacyActivationFailed(\App\Models\Domain $domain, string $reason): void
    {
        $this->alertAdmins('notify_admin_payment', 'ID Protection sudah dibayar tapi GAGAL diaktifkan', [
            'Domain' => $domain->domain_name,
            'Klien' => $domain->client->name ?? '—',
            'Alasan' => $reason,
        ], route('admin.domains.details', $domain), 'warning');
    }

    /**
     * Hosting sudah dibuat tapi email kredensial ke klien GAGAL terkirim.
     * Password tidak disimpan, jadi admin perlu kirim ulang (tombol
     * "kirim info" di detail akun, yang membuat password baru).
     * Dicatat juga di log aktivitas karena email admin bisa ikut gagal
     * kalau penyebabnya SMTP.
     */
    public function credentialEmailFailed(Invoice $invoice, array $domains, string $reason): void
    {
        ActivityLog::record(
            'service',
            'Email kredensial hosting GAGAL terkirim',
            'Invoice ' . $invoice->invoice_number . ' — ' . implode(', ', $domains) . '. Kirim ulang dari detail hosting account.',
            route('admin.hosting-accounts'),
            'danger',
            $invoice->client_id,
        );

        $this->alertAdmins('notify_admin_payment', 'Email kredensial hosting GAGAL terkirim', [
            'Invoice' => $invoice->invoice_number,
            'Klien' => $invoice->client->name ?? '—',
            'Domain' => implode(', ', $domains),
            'Alasan' => $reason,
            'Tindakan' => 'Buka detail hosting account lalu klik kirim info (membuat password baru).',
        ], route('admin.hosting-accounts'), 'danger');
    }

    /**
     * Domain untuk TLD yang mewajibkan data kelayakan menunggu tindakan admin.
     */
    public function domainNeedsEligibility(\App\Models\Domain $domain, string $tldExt): void
    {
        $this->alertAdmins('notify_admin_payment', "Domain .{$tldExt} butuh data kelayakan tambahan", [
            'Domain' => $domain->domain_name,
            'Klien' => $domain->client->name ?? '—',
            'TLD' => ".{$tldExt}",
        ], route('admin.domains.details', $domain), 'warning');
    }

    /**
     * TLD Indonesia — klien perlu diberi tahu untuk mengunggah dokumen.
     */
    public function domainNeedsDocuments(\App\Models\Domain $domain, string $tldExt): void
    {
        $client = $domain->client;

        if ($client && $this->enabled('notify_paid')) {
            $this->send($client, new \App\Notifications\DomainNeedsDocuments($domain, $tldExt), 'domain:documents:' . $domain->id);
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
     * Staf membalas tiket biasa (bukan catatan internal).
     */
    public function ticketRepliedByAdmin(\App\Models\Ticket $ticket, \App\Models\TicketReply $reply): void
    {
        if ($ticket->client && $this->enabled('notify_ticket_reply')) {
            $this->send($ticket->client, new \App\Notifications\TicketReplied($ticket, $reply), 'ticket:reply:' . $reply->id);
        }
    }

    /**
     * Klien membalas tiket — kirim alert kepada admin.
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
    /**
     * Saldo klien jadi minus karena refund/chargeback top-up menarik dana
     * yang sudah terpakai.
     */
    public function negativeBalance(Client $client, float $balance, string $invoiceNumber): void
    {
        $this->alertAdmins('notify_admin_payment', 'Saldo klien MINUS (refund/chargeback top-up)', [
            'Klien' => $client->name,
            'Saldo' => 'Rp ' . number_format($balance, 0, ',', '.'),
            'Invoice' => $invoiceNumber,
            'Tindakan' => 'Tagih selisihnya atau sesuaikan manual setelah dicek.',
        ], route('admin.clients.details', $client), 'danger');
    }

    /**
     * Penyesuaian saldo manual oleh admin — selalu diberi tahu ke admin
     * lain supaya tidak ada perubahan uang yang diam-diam.
     */
    public function balanceAdjusted(Client $client, float $amount, string $reason, string $adminName): void
    {
        $this->alertAdmins('notify_admin_payment', 'Saldo klien disesuaikan manual', [
            'Klien' => $client->name,
            'Perubahan' => ($amount > 0 ? '+' : '-') . 'Rp ' . number_format(abs($amount), 0, ',', '.'),
            'Saldo baru' => 'Rp ' . number_format((float) $client->balance, 0, ',', '.'),
            'Alasan' => $reason,
            'Oleh' => $adminName,
        ], route('admin.clients.details', $client), 'warning');
    }

    /**
     * Saldo di tabel clients tidak sama dengan jumlah buku besar.
     *
     * @param  array<int, array{client_id:int,name:string,balance:string,ledger:string}>  $rows
     */
    public function balanceMismatch(array $rows): void
    {
        $lines = array_map(
            fn ($r) => "#{$r['client_id']} {$r['name']}: saldo Rp {$r['balance']} vs buku besar Rp {$r['ledger']}",
            array_slice($rows, 0, 10),
        );

        $this->alertAdmins('notify_admin_payment', 'Saldo klien tidak cocok dengan buku besar', [
            'Jumlah klien' => (string) count($rows),
            'Contoh' => implode("\n", $lines),
            'Tindakan' => 'Jalankan lumora:reconcile-billing untuk daftar lengkap, lalu periksa mutasi saldo.',
        ], null, 'danger');
    }

    private function alertAdmins(string $settingKey, string $judul, array $details, ?string $link = null, string $level = 'info'): void
    {
        if (! $this->enabled($settingKey)) {
            return;
        }

        foreach ($this->admins() as $admin) {
            $this->send($admin, new AdminAlert($judul, $details, $link, $level), 'admin-alert:' . sha1($admin->getKey() . '|' . $judul . '|' . ($link ?? '') . '|' . json_encode($details)));
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
     * Apakah jenis notifikasi ini diaktifkan? Default menyala.
     */
    private function enabled(string $key): bool
    {
        return Setting::get($key, '1') === '1';
    }

    /**
     * Kirim lewat antrean dengan deduplikasi; antrean pending tidak dikirim
     * ulang tiap kali pemicu yang sama dipanggil.
     */
    private function send(object $notifiable, $notification, ?string $eventKey = null, ?bool &$duplicate = null): bool
    {
        if ($duplicate !== null) {
            $duplicate = false;
        }

        $delivery = null;

        try {
            $dedupeKey = $eventKey
                ? hash('sha256', implode('|', [
                    $notifiable::class,
                    $notifiable->getKey(),
                    $notification::class,
                    $eventKey,
                ]))
                : (string) str()->uuid();

            $delivery = NotificationDelivery::firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'notifiable_type' => $notifiable::class,
                    'notifiable_id' => $notifiable->getKey(),
                    'notification_type' => $notification::class,
                    'event_key' => $eventKey,
                    'status' => 'pending',
                ],
            );

            if ($delivery->status === 'sent'
                || ($delivery->status === 'pending' && ! $delivery->wasRecentlyCreated)) {
                if ($duplicate !== null) {
                    $duplicate = true;
                }

                return false;
            }

            if ($delivery->status === 'failed') {
                $delivery->forceFill([
                    'status' => 'pending',
                    'failed_at' => null,
                    'error' => null,
                ])->save();
            }

            DeliverNotification::dispatch($delivery->id, $notifiable, $notification);

            return true;
        } catch (Throwable $e) {
            if ($delivery && $delivery->status === 'pending') {
                $delivery->forceFill([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error' => mb_substr($e->getMessage(), 0, 4000),
                ])->save();
            }

            Log::warning('Notifikasi gagal diantrikan: ' . $e->getMessage(), [
                'penerima' => $notifiable->email ?? '—',
                'jenis' => $notification::class,
            ]);

            return false;
        }
    }
}