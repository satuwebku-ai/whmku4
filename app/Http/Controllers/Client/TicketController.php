<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TicketController extends Controller
{
    use AuthorizesClientOwnership;

    public function tickets(Request $request): View
    {
        return view('client.tickets.index', $this->ticketsData($request));
    }

    public function ticketsBootstrap(Request $request): View
    {
        return view('client.tickets.index', $this->ticketsData($request));
    }

    private function ticketsData(Request $request): array
    {
        $tickets = Auth::guard('client')->user()
            ->tickets()
            ->when($request->status === 'open', fn ($q) => $q->where('status', '!=', 'closed'))
            ->when($request->status === 'closed', fn ($q) => $q->where('status', 'closed'))
            ->withCount('publicReplies')
            ->orderByDesc('last_reply_at')
            ->paginate(10)
            ->withQueryString();

        return compact('tickets');
    }

    public function ticket(Ticket $ticket): View
    {
        return view('client.tickets.show', $this->ticketData($ticket));
    }

    public function ticketBootstrap(Ticket $ticket): View
    {
        return view('client.tickets.show', $this->ticketData($ticket));
    }

    private function ticketData(Ticket $ticket): array
    {
        $this->authorizeOwner($ticket);

        $ticket->load(['publicReplies.admin', 'publicReplies.client', 'publicReplies.attachments']);

        return compact('ticket');
    }

    public function create(): View
    {
        return view('client.tickets.create', $this->createData());
    }

    public function createBootstrap(): View
    {
        return view('client.tickets.create', $this->createData());
    }

    private function createData(): array
    {
        $client = Auth::guard('client')->user();

        return [
            'services' => $client->hostingAccounts()->get(),
            'domains'  => $client->domains()->get(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'subject'            => ['required', 'string', 'max:255'],
            'department'         => ['required', 'in:support,billing,sales,abuse'],
            'priority'           => ['required', 'in:low,medium,high'],
            'message'            => ['required', 'string'],
            'hosting_account_id' => ['nullable', 'exists:hosting_accounts,id'],
            'attachments'        => ['nullable', 'array', 'max:5'],
            'attachments.*'      => ['file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,zip'],
        ], [
            'attachments.max' => 'Maksimal 5 berkas lampiran.',
            'attachments.*.max' => 'Setiap berkas maksimal 5 MB.',
            'attachments.*.mimes' => 'Format berkas harus jpg, png, pdf, txt, log, atau zip.',
        ]);

        // Cegah klien melampirkan layanan milik orang lain.
        if (! empty($data['hosting_account_id'])) {
            abort_unless(
                $client->hostingAccounts()->whereKey($data['hosting_account_id'])->exists(),
                403
            );
        }

        $ticket = Ticket::create([
            'client_id'          => $client->id,
            'subject'            => $data['subject'],
            'department'         => $data['department'],
            'priority'           => $data['priority'],
            'hosting_account_id' => $data['hosting_account_id'] ?? null,
            'status'             => 'open',
        ]);

        $reply = $ticket->replies()->create([
            'client_id' => $client->id,
            'message'   => $data['message'],
        ]);

        $this->storeAttachments($reply, $request);

        // Beritahu admin ada tiket baru + catat ke log aktivitas.
        try {
            app(\App\Services\Notification\NotificationService::class)->ticketCreated($ticket->load('client'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notifikasi tiket gagal: ' . $e->getMessage());
        }

        return redirect()->route('client.tickets.show', $ticket)
            ->with('success', "Tiket {$ticket->ticket_number} berhasil dibuat. Tim kami akan segera membalas.");
    }

    /**
     * Klien membalas tiketnya sendiri.
     */
    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeOwner($ticket);

        if ($ticket->isClosed()) {
            return back()->with('error', 'Tiket ini sudah ditutup. Silakan buat tiket baru.');
        }

        $data = $request->validate([
            'message'        => ['required', 'string'],
            'attachments'    => ['nullable', 'array', 'max:5'],
            'attachments.*'  => ['file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,zip'],
        ], [
            'attachments.max' => 'Maksimal 5 berkas lampiran.',
            'attachments.*.max' => 'Setiap berkas maksimal 5 MB.',
            'attachments.*.mimes' => 'Format berkas harus jpg, png, pdf, txt, log, atau zip.',
        ]);

        $reply = $ticket->replies()->create([
            'client_id' => Auth::guard('client')->id(),
            'message'   => $data['message'],
        ]);

        $this->storeAttachments($reply, $request);

        // Status berubah supaya tiket naik ke atas antrean staf.
        $ticket->update(['status' => 'customer_reply', 'last_reply_at' => now()]);

        app(\App\Services\Notification\NotificationService::class)->ticketRepliedByClient($ticket, $reply);

        return back()->with('success', 'Balasan Anda terkirim.');
    }

    /**
     * Simpan semua berkas yang diunggah (field "attachments[]") sebagai
     * baris TicketAttachment terpisah, dipakai bersama oleh store() & reply().
     */
    private function storeAttachments(\App\Models\TicketReply $reply, Request $request): void
    {
        foreach ($request->file('attachments', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $reply->attachments()->create([
                'path'          => $file->store('ticket-attachments', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
            ]);
        }
    }

    public function close(Ticket $ticket): RedirectResponse
    {
        $this->authorizeOwner($ticket);

        $ticket->update(['status' => 'closed', 'closed_at' => now()]);

        return back()->with('success', 'Tiket ditutup. Terima kasih.');
    }
}
