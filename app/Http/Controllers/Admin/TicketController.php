<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function tickets(Request $request): View
    {
        return $this->renderList($request, null);
    }

    public function ticketsBootstrap(Request $request): View
    {
        return view('admin.tickets.index', $this->listData($request, null));
    }

    public function open(Request $request): View
    {
        return $this->renderList($request, 'open');
    }

    public function openBootstrap(Request $request): View
    {
        return view('admin.tickets.index', $this->listData($request, 'open'));
    }

    public function answered(Request $request): View
    {
        return $this->renderList($request, 'answered');
    }

    public function answeredBootstrap(Request $request): View
    {
        return view('admin.tickets.index', $this->listData($request, 'answered'));
    }

    public function customerReply(Request $request): View
    {
        return $this->renderList($request, 'customer_reply');
    }

    public function customerReplyBootstrap(Request $request): View
    {
        return view('admin.tickets.index', $this->listData($request, 'customer_reply'));
    }

    public function closed(Request $request): View
    {
        return $this->renderList($request, 'closed');
    }

    public function closedBootstrap(Request $request): View
    {
        return view('admin.tickets.index', $this->listData($request, 'closed'));
    }

    private function renderList(Request $request, ?string $status): View
    {
        return view('admin.tickets.index', $this->listData($request, $status));
    }

    private function listData(Request $request, ?string $status): array
    {
        $tickets = Ticket::query()
            ->with(['client', 'assignee'])
            ->withCount('replies')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('ticket_number', 'like', "%{$request->search}%")
                  ->orWhere('subject', 'like', "%{$request->search}%");
            }))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            // Tiket yang butuh perhatian naik ke atas, lalu urut balasan terbaru.
            ->orderByRaw("FIELD(status, 'customer_reply', 'open', 'answered', 'closed')")
            ->orderByDesc('last_reply_at')
            ->paginate(10)
            ->withQueryString();

        return ['tickets' => $tickets, 'activeStatus' => $status];
    }

    public function details(Ticket $ticket): View
    {
        $ticket->load(['client', 'assignee', 'replies.admin', 'replies.client', 'replies.attachments', 'hostingAccount', 'domain', 'invoice']);
        $admins = Admin::where('is_active', true)->orderBy('name')->get();

        return view('admin.tickets.details', compact('ticket', 'admins'));
    }

    public function detailsBootstrap(Ticket $ticket): View
    {
        $ticket->load(['client', 'assignee', 'replies.admin', 'replies.client', 'replies.attachments', 'hostingAccount', 'domain', 'invoice']);
        $admins = Admin::where('is_active', true)->orderBy('name')->get();

        return view('admin.tickets.details', compact('ticket', 'admins'));
    }

    /**
     * Ambil kode EPP dari registrar untuk DIPRATINJAU admin dulu -- tidak
     * langsung dikirim ke klien. Dipisah dari approveTransferCode() supaya
     * admin bisa lihat kodenya dulu sebelum benar-benar memutuskan kirim.
     */
    public function previewTransferCode(Ticket $ticket): RedirectResponse
    {
        if (! $ticket->domain || ! $ticket->domain->registrar) {
            return back()->with('error', 'Tiket ini tidak terhubung ke domain dengan registrar yang valid.');
        }

        $service = \App\Services\Domain\DomainRegistrarFactory::make($ticket->domain->registrar);

        if (! method_exists($service, 'getAuthCode')) {
            return back()->with('error', 'Registrar domain ini tidak mendukung pengambilan kode transfer.');
        }

        $result = $service->getAuthCode($ticket->domain->domain_name);

        if (! $result['success'] || ! $result['code']) {
            return back()->with('error', 'Gagal mengambil kode transfer: ' . $result['message']);
        }

        // Kode dipreview lewat session flash sekali tampil -- tidak
        // disimpan permanen di database, sama seperti alur lama sebelum
        // diganti jadi lewat tiket, cuma sekarang yang lihat admin dulu.
        return back()->with('preview_transfer_code', $result['code']);
    }

    public function approveTransferCode(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        if (! $ticket->client) {
            return back()->with('error', 'Tiket ini tidak terhubung ke klien.');
        }

        $ticket->client->notify(new \App\Notifications\TransferCodeApproved($ticket->domain, $data['code']));

        $ticket->replies()->create([
            'admin_id' => auth('admin')->id(),
            'message'  => 'Kode transfer sudah disetujui dan dikirim ke email Anda. Silakan cek inbox (dan folder spam bila perlu).',
        ]);

        $ticket->update(['status' => 'answered', 'last_reply_at' => now()]);

        return back()->with('success', 'Kode transfer dikirim ke email klien, dan tiket ditandai terjawab.');
    }

    public function create(): View
    {
        $clients = Client::orderBy('name')->get();

        return view('admin.tickets.form', ['ticket' => new Ticket(), 'clients' => $clients]);
    }

    public function createBootstrap(): View
    {
        $clients = Client::orderBy('name')->get();

        return view('admin.tickets.form', ['ticket' => new Ticket(), 'clients' => $clients]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id'      => ['required', 'exists:clients,id'],
            'subject'        => ['required', 'string', 'max:255'],
            'department'     => ['required', 'in:support,billing,sales,abuse'],
            'priority'       => ['required', 'in:low,medium,high,urgent'],
            'message'        => ['required', 'string'],
            'attachments'    => ['nullable', 'array', 'max:5'],
            'attachments.*'  => ['file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,zip'],
        ], [
            'attachments.max' => 'Maksimal 5 berkas lampiran per pesan.',
            'attachments.*.max' => 'Setiap berkas maksimal 5 MB.',
            'attachments.*.mimes' => 'Format berkas harus jpg, png, pdf, txt, log, atau zip.',
        ]);

        $ticket = Ticket::create([
            'client_id'  => $data['client_id'],
            'subject'    => $data['subject'],
            'department' => $data['department'],
            'priority'   => $data['priority'],
            'status'     => 'open',
        ]);

        // Pesan pertama dicatat atas nama klien, karena tiket dibuatkan
        // admin mewakili keluhan klien.
        $reply = $ticket->replies()->create([
            'client_id' => $data['client_id'],
            'message'   => $data['message'],
        ]);

        $this->storeAttachments($reply, $request);

        return redirect()->route('admin.tickets.details', $ticket)
            ->with('success', "Tiket {$ticket->ticket_number} berhasil dibuat.");
    }

    /**
     * Balas tiket sebagai staf, atau simpan catatan internal.
     */
    public function reply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ticket_id'        => ['required', 'exists:tickets,id'],
            'message'          => ['required', 'string'],
            'is_internal_note' => ['nullable', 'boolean'],
            'attachments'      => ['nullable', 'array', 'max:5'],
            'attachments.*'    => ['file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,zip'],
        ], [
            'attachments.max' => 'Maksimal 5 berkas lampiran per balasan.',
            'attachments.*.max' => 'Setiap berkas maksimal 5 MB.',
            'attachments.*.mimes' => 'Format berkas harus jpg, png, pdf, txt, log, atau zip.',
        ]);

        $ticket = Ticket::findOrFail($data['ticket_id']);
        $isNote = $request->boolean('is_internal_note');

        $reply = new TicketReply([
            'admin_id'         => Auth::guard('admin')->id(),
            'message'          => $data['message'],
            'is_internal_note' => $isNote,
        ]);

        $ticket->replies()->save($reply);

        $this->storeAttachments($reply, $request);

        // Catatan internal tidak mengubah status tiket — klien tidak
        // melihatnya, jadi tiket tetap dianggap belum dijawab.
        if (! $isNote) {
            $ticket->update([
                'status' => 'answered',
                'last_reply_at' => now(),
            ]);

            app(\App\Services\Notification\NotificationService::class)->ticketRepliedByAdmin($ticket, $reply);
        }

        return back()->with('success', $isNote ? 'Catatan internal tersimpan.' : 'Balasan terkirim.');
    }

    /**
     * Simpan semua berkas yang diunggah (field "attachments[]") sebagai
     * baris TicketAttachment terpisah, dipakai bersama oleh store() & reply().
     */
    private function storeAttachments(TicketReply $reply, Request $request): void
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

    /**
     * Tutup tiket.
     */
    public function close(Request $request): RedirectResponse
    {
        $ticket = Ticket::findOrFail($request->input('ticket_id'));

        $ticket->update(['status' => 'closed', 'closed_at' => now()]);

        return back()->with('success', "Tiket {$ticket->ticket_number} ditutup.");
    }

    /**
     * Buka kembali tiket yang sudah ditutup.
     */
    public function reopen(Request $request): RedirectResponse
    {
        $ticket = Ticket::findOrFail($request->input('ticket_id'));

        $ticket->update(['status' => 'customer_reply', 'closed_at' => null, 'last_reply_at' => now()]);

        return back()->with('success', "Tiket {$ticket->ticket_number} dibuka kembali.");
    }

    /**
     * Tugaskan tiket ke staf tertentu.
     */
    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ticket_id'   => ['required', 'exists:tickets,id'],
            'assigned_to' => ['nullable', 'exists:admins,id'],
        ]);

        $ticket = Ticket::findOrFail($data['ticket_id']);
        $ticket->update(['assigned_to' => $data['assigned_to'] ?: null]);

        return back()->with('success', $data['assigned_to']
            ? 'Tiket ditugaskan ke ' . ($ticket->fresh()->assignee->name ?? 'staf') . '.'
            : 'Penugasan tiket dilepas.');
    }

    /**
     * Ubah prioritas tiket.
     */
    public function priority(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'exists:tickets,id'],
            'priority'  => ['required', 'in:low,medium,high,urgent'],
        ]);

        $ticket = Ticket::findOrFail($data['ticket_id']);
        $ticket->update(['priority' => $data['priority']]);

        return back()->with('success', 'Prioritas tiket diperbarui.');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $ticket->delete();

        return redirect()->route('admin.tickets')->with('success', 'Tiket berhasil dihapus.');
    }
}
