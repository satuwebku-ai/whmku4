<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Penutupan otomatis tiket dalam DUA tahap, sama seperti live chat:
 *
 *  1. Tiket berstatus 'answered' (staf sudah membalas, menunggu klien) yang
 *     tidak dibalas klien selama N hari -> sistem menambahkan balasan
 *     "Apakah masalah sudah selesai?" dan mengirim notifikasi ke klien.
 *  2. Kalau sesudah pertanyaan itu M hari klien tetap diam -> tiket ditutup.
 *
 * Klien yang membalas membuat status tiket menjadi 'customer_reply', sehingga
 * tahap 2 tidak pernah menutupnya; penanda idle_prompted_at dibersihkan di
 * pemanggilan berikutnya.
 *
 * Pengaturan (tabel settings, ada default):
 *   ticket_idle_prompt_days -- default 3
 *   ticket_idle_close_days  -- default 2
 */
class CloseInactiveTickets extends Command
{
    protected $signature = 'lumora:close-inactive-tickets
                            {--days= : Batas tanpa balasan klien sebelum ditanya (hari)}
                            {--close-after= : Jeda sesudah pertanyaan sebelum ditutup (hari)}';

    protected $description = 'Tanya klien "masih perlu bantuan?" lalu tutup otomatis tiket yang tidak dibalas.';

    public function handle(): int
    {
        $promptDays = max(1, (int) ($this->option('days') ?: Setting::get('ticket_idle_prompt_days', 3)));
        $closeDays = max(1, (int) ($this->option('close-after') ?: Setting::get('ticket_idle_close_days', 2)));

        // Klien sudah membalas / tiket dibuka lagi -> tanda tanya dihapus.
        Ticket::whereNotNull('idle_prompted_at')
            ->where('status', '!=', 'answered')
            ->update(['idle_prompted_at' => null]);

        $prompted = $this->promptStale($promptDays, $closeDays);
        $closed = $this->closeUnanswered($closeDays);

        $this->info("Selesai -- {$prompted} tiket ditanya, {$closed} tiket ditutup otomatis.");

        return self::SUCCESS;
    }

    private function promptStale(int $promptDays, int $closeDays): int
    {
        $tickets = Ticket::where('status', 'answered')
            ->whereNull('idle_prompted_at')
            ->where('last_reply_at', '<', now()->subDays($promptDays))
            ->get();

        foreach ($tickets as $ticket) {
            $reply = TicketReply::create([
                'ticket_id' => $ticket->id,
                'message' => "Halo, apakah masalah Anda sudah teratasi?\n\n"
                    . "Kalau masih membutuhkan bantuan, silakan balas tiket ini. "
                    . "Kalau tidak ada balasan dalam {$closeDays} hari, tiket ini akan kami tutup otomatis.",
                'is_internal_note' => false,
            ]);

            // last_reply_at SENGAJA tidak diubah, supaya tiket tetap terlihat
            // berumur sama di daftar admin; yang dipakai hitungan adalah idle_prompted_at.
            $ticket->update(['idle_prompted_at' => now()]);

            try {
                app(NotificationService::class)->ticketRepliedByAdmin($ticket, $reply);
            } catch (Throwable $e) {
                $this->warn("  Notifikasi gagal untuk {$ticket->ticket_number}: {$e->getMessage()}");
            }

            $this->line("  Ditanya: {$ticket->ticket_number}");
        }

        return $tickets->count();
    }

    private function closeUnanswered(int $closeDays): int
    {
        $tickets = Ticket::where('status', 'answered')
            ->whereNotNull('idle_prompted_at')
            ->where('idle_prompted_at', '<', now()->subDays($closeDays))
            ->get();

        foreach ($tickets as $ticket) {
            TicketReply::create([
                'ticket_id' => $ticket->id,
                'message' => 'Tiket ini kami tutup otomatis karena tidak ada balasan. Kalau masih ada kendala, silakan balas tiket ini dan kami akan membukanya kembali.',
                'is_internal_note' => false,
            ]);

            $ticket->update([
                'status' => 'closed',
                'closed_at' => now(),
                'idle_prompted_at' => null,
            ]);

            $this->line("  Ditutup: {$ticket->ticket_number}");
        }

        return $tickets->count();
    }
}
