<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.chats.index', $this->indexData($request));
    }

    public function indexBootstrap(Request $request): View
    {
        return view('admin.chats.index', $this->indexData($request));
    }

    private function indexData(Request $request): array
    {
        $conversations = ChatConversation::query()
            ->with(['client', 'assignedAdmin'])
            ->when($request->status === 'closed', fn ($q) => $q->where('status', 'closed'))
            ->when($request->status !== 'closed', fn ($q) => $q->where('status', 'open'))
            ->orderByDesc('unread_for_admin')
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'open' => ChatConversation::open()->count(),
            'unread' => ChatConversation::where('unread_for_admin', '>', 0)->count(),
            'closed' => ChatConversation::where('status', 'closed')->count(),
        ];

        return compact('conversations', 'counts');
    }

    public function show(ChatConversation $chat): View
    {
        $this->markOpened($chat);

        return view('admin.chats.show', ['chat' => $chat]);
    }

    public function showBootstrap(ChatConversation $chat): View
    {
        $this->markOpened($chat);

        return view('admin.chats.show', ['chat' => $chat]);
    }

    private function markOpened(ChatConversation $chat): void
    {
        $chat->load(['client', 'messages.admin', 'assignedAdmin']);

        $admin = Auth::guard('admin')->user();

        // Percakapan yang belum dipegang siapa pun otomatis "diambil"
        // begitu seorang staf membukanya -- supaya tidak ada dua staf
        // balas percakapan yang sama tanpa sadar, dan setiap chat punya
        // penanggung jawab yang jelas.
        if (! $chat->assigned_admin_id) {
            $chat->update(['assigned_admin_id' => $admin->id, 'assigned_at' => now()]);
        }

        // Dibuka admin = pesan pengunjung sudah dibaca.
        $chat->update(['unread_for_admin' => 0]);
    }

    /**
     * Staf lain (bukan yang sedang memegang) bisa ambil alih manual --
     * mis. staf sebelumnya sedang sibuk atau offline.
     */
    public function claim(ChatConversation $chat): RedirectResponse
    {
        $chat->update(['assigned_admin_id' => Auth::guard('admin')->id(), 'assigned_at' => now()]);

        return back()->with('success', 'Percakapan ini sekarang jadi tanggung jawab Anda.');
    }

    /**
     * Ambil pesan baru (dipakai polling di halaman detail).
     */
    public function poll(Request $request, ChatConversation $chat): JsonResponse
    {
        $afterId = (int) $request->input('after', 0);

        $messages = $chat->messages()
            ->with('admin')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get();

        if ($messages->where('sender', 'user')->isNotEmpty()) {
            $chat->update(['unread_for_admin' => 0]);
        }

        return response()->json([
            'messages' => $messages->map->toWidgetArray()->values(),
            'status' => $chat->status,
        ]);
    }

    public function reply(Request $request, ChatConversation $chat): RedirectResponse|JsonResponse
    {
        $admin = Auth::guard('admin')->user();

        // Klaim sekarang benar-benar DITEGAKKAN, bukan sekadar label --
        // kalau percakapan ini sudah dipegang staf LAIN, staf yang
        // sedang login tidak bisa ikut membalas sampai dia sendiri
        // yang mengambil alih lewat tombol "Ambil Alih".
        if ($chat->assigned_admin_id && $chat->assigned_admin_id !== $admin->id) {
            $message = 'Percakapan ini sedang dipegang ' . ($chat->assignedAdmin?->name ?: 'staf lain') . '. Ambil alih dulu kalau ingin membalas.';

            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => $message], 409)
                : back()->with('error', $message);
        }

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        if (blank($data['message'] ?? null) && ! $request->hasFile('attachment')) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => 'Pesan kosong.'], 422)
                : back()->with('error', 'Tulis pesan atau lampirkan berkas.');
        }

        $message = new ChatMessage([
            'sender' => 'admin',
            'admin_id' => $admin->id,
            'message' => $data['message'] ?? null,
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $message->attachment_path = $file->store('chat', 'public');
            $message->attachment_name = $file->getClientOriginalName();
            $message->attachment_mime = $file->getMimeType();
        }

        $chat->messages()->save($message);
        $chat->increment('unread_for_user');
        $chat->update(['last_message_at' => now(), 'status' => 'open']);

        // Untuk percakapan WhatsApp, balasan admin harus benar-benar
        // dikirim ke WhatsApp klien -- kalau cuma tersimpan di database
        // tanpa ini, klien tidak akan pernah melihatnya sama sekali
        // (mereka tidak sedang membuka widget web).
        if ($chat->channel === 'whatsapp' && $chat->phone && filled($message->message)) {
            $sent = app(\App\Notifications\Channels\WhatsAppChannel::class)->dispatch($chat->phone, $message->message);

            if (! $sent) {
                \Illuminate\Support\Facades\Log::warning('Balasan admin gagal dikirim ke WhatsApp klien.', ['conversation_id' => $chat->id]);
            }
        }

        // Setelah membalas, kalau staf ini tidak punya percakapan lain
        // yang masih menunggu balasan, otomatis berikan percakapan
        // TERLAMA yang belum dipegang siapa pun -- supaya staf tidak
        // perlu bolak-balik cek daftar manual, dan tidak ada klien yang
        // ketahanan lama karena percakapannya tidak "kelihatan" siapa pun.
        //
        // Dikunci (lockForUpdate) di dalam transaksi supaya kalau dua
        // staf sama-sama membalas dalam waktu bersamaan, mereka TIDAK
        // sama-sama dapat percakapan berikutnya yang sama.
        $autoAssigned = null;

        $hasOtherWaiting = ChatConversation::where('assigned_admin_id', $admin->id)
            ->where('id', '!=', $chat->id)
            ->where('unread_for_admin', '>', 0)
            ->exists();

        if (! $hasOtherWaiting) {
            $autoAssigned = \Illuminate\Support\Facades\DB::transaction(function () use ($admin) {
                $next = ChatConversation::waitingUnassigned()
                    ->oldest('last_message_at')
                    ->lockForUpdate()
                    ->first();

                if ($next) {
                    $next->update(['assigned_admin_id' => $admin->id, 'assigned_at' => now()]);
                }

                return $next;
            });
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message->load('admin')->toWidgetArray(),
                'auto_assigned' => $autoAssigned ? [
                    'id' => $autoAssigned->id,
                    'name' => $autoAssigned->display_name,
                    'url' => route('admin.chats.show', $autoAssigned),
                ] : null,
            ]);
        }

        return back();
    }

    public function close(ChatConversation $chat): RedirectResponse
    {
        $chat->update(['status' => 'closed']);

        return redirect()->route('admin.chats')->with('success', 'Percakapan ditutup.');
    }

    /**
     * Jadikan percakapan chat sebagai tiket support -- transkrip
     * percakapan disalin jadi pesan pertama tiket, supaya konteksnya
     * tidak hilang. Balasan SELANJUTNYA berjalan lewat sistem tiket
     * (bukan lagi chat), yang otomatis mengirim email ke klien setiap
     * staf membalas -- chat sendiri tidak pernah memberi tahu klien
     * lewat email kalau mereka sudah menutup tab browser-nya.
     */
    public function convertToTicket(ChatConversation $chat): RedirectResponse
    {
        if (! $chat->client_id) {
            return back()->with('error', 'Percakapan ini dari pengunjung anonim (belum login) — tiket cuma bisa dibuat untuk klien terdaftar.');
        }

        if ($chat->ticket_id) {
            return redirect()->route('admin.tickets.details', $chat->ticket_id);
        }

        $chat->load('messages');

        $ticket = \App\Models\Ticket::create([
            'client_id'  => $chat->client_id,
            'subject'    => 'Live Chat — ' . now()->format('d M Y H:i'),
            'department' => 'support',
            'priority'   => 'medium',
            'status'     => 'open',
        ]);

        $transkrip = $chat->messages->map(function ($m) use ($chat) {
            $pengirim = $m->sender === 'admin' ? ($m->admin->name ?? 'Staf') : $chat->display_name;

            return "{$pengirim}: {$m->message}";
        })->implode("\n");

        $ticket->replies()->create([
            'client_id' => $chat->client_id,
            'message'   => "[Dipindahkan dari Live Chat]\n\n" . $transkrip,
        ]);

        $chat->update(['ticket_id' => $ticket->id, 'status' => 'closed']);

        return redirect()->route('admin.tickets.details', $ticket)
            ->with('success', 'Percakapan berhasil dijadikan tiket ' . $ticket->ticket_number . '. Balasan Anda selanjutnya di sini otomatis dikirim ke email klien.');
    }

    public function destroy(ChatConversation $chat): RedirectResponse
    {
        $chat->delete();

        return redirect()->route('admin.chats')->with('success', 'Percakapan dihapus.');
    }

    /**
     * Dipoll dari SEMUA halaman admin (lewat layout bersama) -- bukan
     * cuma halaman Live Chat -- supaya badge sidebar & suara notifikasi
     * tetap jalan walau staf sedang buka halaman lain. Sengaja dibuat
     * seringan mungkin (cuma hitung angka, tidak load data percakapan).
     */
    public function globalStatus(): JsonResponse
    {
        $adminId = Auth::guard('admin')->id();

        return response()->json([
            'unassigned_waiting' => ChatConversation::waitingUnassigned()->count(),
            'my_unread' => ChatConversation::where('assigned_admin_id', $adminId)
                ->where('unread_for_admin', '>', 0)
                ->count(),
        ]);
    }
}
