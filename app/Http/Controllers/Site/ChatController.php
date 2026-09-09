<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Endpoint untuk widget chat di halaman publik dan area klien.
 *
 * Memakai polling, bukan WebSocket: hosting cPanel umumnya tidak mengizinkan
 * proses yang berjalan terus-menerus, sehingga WebSocket tidak bisa dipakai.
 * Polling tiap beberapa detik jauh lebih sederhana dan cukup untuk volume
 * percakapan sebuah penyedia hosting kecil-menengah.
 */
class ChatController extends Controller
{
    /**
     * Ambil percakapan berjalan beserta pesannya.
     */
    public function fetch(Request $request): JsonResponse
    {
        $conversation = $this->findConversation($request);

        if (! $conversation) {
            return response()->json([
                'conversation' => null,
                'messages' => [],
                'greeting' => $this->greetingLines(),
            ]);
        }

        $afterId = (int) $request->input('after', 0);

        $messages = $conversation->messages()
            ->with('admin')
            ->when($afterId, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(100)
            ->get();

        // Pesan dari admin dianggap terbaca begitu widget mengambilnya.
        if ($conversation->unread_for_user > 0 && $afterId === 0) {
            $conversation->update(['unread_for_user' => 0]);
        } elseif ($messages->where('sender', 'admin')->isNotEmpty()) {
            $conversation->decrement('unread_for_user', min(
                $messages->where('sender', 'admin')->count(),
                $conversation->unread_for_user
            ));
        }

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
            ],
            'messages' => $messages->map->toWidgetArray()->values(),
            'greeting' => $conversation->messages()->exists() ? [] : $this->greetingLines(),
        ]);
    }

    /**
     * Kirim pesan. Percakapan dibuat otomatis pada pesan pertama.
     */
    public function send(Request $request): JsonResponse
    {
        // Batasi supaya widget tidak bisa dipakai membanjiri database.
        $key = 'chat-send|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json([
                'ok' => false,
                'message' => 'Terlalu banyak pesan. Tunggu sebentar lalu coba lagi.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $isGuest = ! Auth::guard('client')->check();

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            // Wajib untuk tamu (belum login) -- klien yang sudah login
            // datanya sudah ada di profil, jadi tidak perlu diisi ulang.
            'name'    => [$isGuest ? 'required' : 'nullable', 'string', 'max:100'],
            'email'   => [$isGuest ? 'required' : 'nullable', 'email', 'max:255'],
            'phone'   => [$isGuest ? 'required' : 'nullable', 'string', 'min:8', 'max:20'],
            // Bukti transfer dan tangkapan layar kendala teknis.
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'name.required' => 'Nama wajib diisi sebelum mengirim pesan.',
            'email.required' => 'Email wajib diisi sebelum mengirim pesan.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'phone.required' => 'Nomor telepon wajib diisi sebelum mengirim pesan.',
            'phone.min' => 'Nomor telepon minimal 8 digit.',
            'attachment.max' => 'Ukuran berkas maksimal 5 MB.',
            'attachment.mimes' => 'Berkas harus berupa gambar (JPG/PNG/WEBP) atau PDF.',
        ]);

        if (blank($data['message'] ?? null) && ! $request->hasFile('attachment')) {
            return response()->json(['ok' => false, 'message' => 'Tulis pesan atau lampirkan berkas.'], 422);
        }

        $conversation = $this->findConversation($request) ?? $this->createConversation($request, $data);

        if ($conversation->status === 'closed') {
            // Penugasan staf lama DIRESET juga -- kalau tidak, percakapan
            // yang baru dibuka lagi akan "menempel" ke staf yang sama
            // dari sesi sebelumnya, padahal seharusnya masuk antrian
            // lagi dari awal supaya staf mana pun yang sedang available
            // bisa mengambilnya.
            $conversation->update(['status' => 'open', 'assigned_admin_id' => null, 'assigned_at' => null]);
        }

        $message = new ChatMessage([
            'sender' => 'user',
            'message' => $data['message'] ?? null,
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $message->attachment_path = $file->store('chat', 'public');
            $message->attachment_name = $file->getClientOriginalName();
            $message->attachment_mime = $file->getMimeType();
        }

        $conversation->messages()->save($message);

        $conversation->increment('unread_for_admin');
        $conversation->update(['last_message_at' => now()]);

        // Bot dipanggil SETELAH pesan pengunjung tersimpan -- kalau
        // nonaktif atau API gagal, ini diam-diam dilewati (lihat
        // AiChatService::reply()), percakapan tetap berjalan normal
        // tanpa balasan bot.
        $botMessage = (new \App\Services\Chat\AiChatService())->reply($conversation);

        // Catat sekali per percakapan saja, supaya daftar aktivitas tidak
        // dibanjiri satu baris per pesan.
        if ($conversation->messages()->where('sender', 'user')->count() === 1) {
            ActivityLog::record(
                'ticket',
                'Chat baru dari ' . $conversation->display_name,
                Str::limit($data['message'] ?? 'Mengirim lampiran', 80),
                route('admin.chats.show', $conversation),
                'warning',
                $conversation->client_id,
            );
        }

        return response()->json([
            'ok' => true,
            'message' => $message->load('admin')->toWidgetArray(),
            'bot_message' => $botMessage?->toWidgetArray(),
        ]);
    }

    /**
     * Cari percakapan milik pengunjung/klien saat ini.
     *
     * Aturan kadaluwarsa (supaya klien lama tidak "menempel" ke
     * percakapan yang sudah lama tidak aktif):
     * - Tamu (belum login): dianggap kadaluwarsa kalau tidak ada
     *   aktivitas > 10 menit -- widget-nya mulai bersih dari awal lagi.
     * - Klien (sudah login): kalau percakapan sudah DITUTUP dan sudah
     *   > 30 menit sejak pesan terakhir, dianggap kadaluwarsa juga --
     *   tapi kalau MASIH dalam 30 menit, tetap lanjutkan yang lama
     *   (klien tidak kehilangan konteks kalau baru saja ditutup).
     */
    private function findConversation(Request $request): ?ChatConversation
    {
        if ($client = Auth::guard('client')->user()) {
            $conversation = ChatConversation::where('client_id', $client->id)->latest('id')->first();

            if (! $conversation) {
                return null;
            }

            if ($conversation->status === 'closed'
                && $conversation->last_message_at
                && $conversation->last_message_at->diffInMinutes(now()) > 30) {
                return null;
            }

            return $conversation;
        }

        $token = $request->session()->get('chat_token');

        if (! $token) {
            return null;
        }

        $conversation = ChatConversation::where('guest_token', $token)->first();

        if (! $conversation) {
            return null;
        }

        if ($conversation->last_message_at && $conversation->last_message_at->diffInMinutes(now()) > 10) {
            // Bukan dihapus (riwayatnya tetap ada di admin, lihat
            // toWidgetArray/ChatController Admin) -- widget klien saja
            // yang dianggap mulai dari percakapan baru.
            return null;
        }

        return $conversation;
    }

    private function createConversation(Request $request, array $data): ChatConversation
    {
        $client = Auth::guard('client')->user();

        $token = $client ? null : Str::random(48);

        if ($token) {
            $request->session()->put('chat_token', $token);
        }

        return ChatConversation::create([
            'guest_token' => $token,
            'client_id' => $client?->id,
            'name' => $client?->name ?? ($data['name'] ?? null),
            'email' => $client?->email ?? ($data['email'] ?? null),
            'phone' => $client?->phone ?? ($data['phone'] ?? null),
            'status' => 'open',
            'last_message_at' => now(),
            'page_url' => $request->headers->get('referer'),
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Pesan sambutan otomatis, diatur admin di Pengaturan → Live Chat.
     */
    private function greetingLines(): array
    {
        $lines = [];

        $sambutan = Setting::get('chat_greeting_1', 'Selamat datang! Ada yang bisa kami bantu?');
        $promo = Setting::get('chat_greeting_2');

        if (filled($sambutan)) {
            $lines[] = $sambutan;
        }

        if (filled($promo)) {
            $lines[] = $promo;
        }

        return $lines;
    }
}
