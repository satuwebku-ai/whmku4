<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Ticket;
use App\Notifications\PromoBroadcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ActivityController extends Controller
{

    public function activitiesBootstrap(Request $request): View
    {
        return view('admin.activities.index', $this->activitiesData($request));
    }

    /**
     * Data terbaru untuk dropdown notifikasi di panel admin.
     * Dipanggil berkala supaya admin tidak perlu memuat ulang halaman.
     */
    public function feed(): JsonResponse
    {
        $items = ActivityLog::query()
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (ActivityLog $activity) => [
                'id' => $activity->id,
                'title' => $activity->title,
                'description' => $activity->description,
                'level' => $activity->level,
                'read' => $activity->read_at !== null,
                'created_human' => $activity->created_at?->diffForHumans() ?? '',
                'url' => route('admin.activities.open', $activity),
            ]);

        return response()->json([
            'unread_count' => ActivityLog::unread()->count(),
            'items' => $items,
            'tickets_needing_attention' => Ticket::whereIn('status', ['open', 'customer_reply'])->count(),
        ]);
    }

    private function activitiesData(Request $request): array
    {
        $activities = ActivityLog::query()
            ->with('client')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->boolean('unread'), fn ($q) => $q->unread())
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'all' => ActivityLog::count(),
            'unread' => ActivityLog::unread()->count(),
        ];

        return compact('activities', 'counts');
    }

    /**
     * Tandai semua aktivitas sudah dibaca.
     */
    public function markAllRead(): RedirectResponse
    {
        ActivityLog::unread()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function markRead(ActivityLog $activity): RedirectResponse
    {
        ActivityLog::query()
            ->whereKey($activity->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    /**
     * Tandai notifikasi sudah dibaca lalu arahkan hanya ke alamat di situs ini.
     */
    public function open(ActivityLog $activity, Request $request): RedirectResponse
    {
        ActivityLog::query()
            ->whereKey($activity->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $target = $activity->link;
        if (blank($target) || strlen($target) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $target)) {
            return redirect()->route('admin.activities');
        }

        $parts = parse_url($target);
        if ($parts === false) {
            return redirect()->route('admin.activities');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== '' && ! in_array($scheme, ['http', 'https'], true)) {
            return redirect()->route('admin.activities');
        }

        $host = strtolower($parts['host'] ?? '');
        if ($host !== '') {
            $allowedHosts = array_filter([
                strtolower($request->getHost()),
                strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            ]);

            if (! in_array($host, $allowedHosts, true)) {
                return redirect()->route('admin.activities');
            }

            $target = ($request->isSecure() ? 'https' : 'http')
                . '://' . $request->getHttpHost()
                . ($parts['path'] ?? '/')
                . (isset($parts['query']) ? '?' . $parts['query'] : '')
                . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
        } elseif (! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return redirect()->route('admin.activities');
        }

        return redirect()->to($target);
    }

    public function destroy(ActivityLog $activity): RedirectResponse
    {
        $activity->delete();

        return back()->with('success', 'Catatan aktivitas dihapus.');
    }

    /**
     * Bersihkan catatan lama supaya tabel tidak menumpuk.
     */
    public function clearOld(): RedirectResponse
    {
        $deleted = ActivityLog::whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        return back()->with('success', "{$deleted} catatan lama (sudah dibaca, lebih dari 30 hari) dihapus.");
    }

    public function promoFormBootstrap(): View
    {
        $total = Client::where('status', 'active')->count();
        $optedIn = Client::where('status', 'active')->where('notify_promo', true)->count();

        return view('admin.activities.promo', compact('total', 'optedIn'));
    }

    /**
     * Kirim email/WA promosi ke klien yang bersedia menerimanya.
     */
    public function sendPromo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'isi' => ['required', 'string', 'max:5000'],
            'tautan' => ['nullable', 'url', 'max:255'],
            'label_tautan' => ['nullable', 'string', 'max:50'],
            'konfirmasi' => ['accepted'],
        ], [
            'konfirmasi.accepted' => 'Centang konfirmasi sebelum mengirim.',
        ]);

        // Hanya klien aktif yang tidak menolak promosi. Menghormati ini
        // bukan sekadar sopan — mengirim promo ke orang yang sudah menolak
        // adalah cara tercepat agar domain pengirim ditandai spam.
        $clients = Client::where('status', 'active')
            ->where('notify_promo', true)
            ->whereNotNull('email')
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($clients as $client) {
            try {
                $client->notify(new PromoBroadcast(
                    $data['judul'],
                    $data['isi'],
                    $data['tautan'] ?? null,
                    $data['label_tautan'] ?? null,
                ));
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Promo gagal dikirim: ' . $e->getMessage(), ['client_id' => $client->id]);
            }
        }

        ActivityLog::record(
            'system',
            'Broadcast promo dikirim',
            "\"{$data['judul']}\" — {$sent} terkirim" . ($failed ? ", {$failed} gagal" : ''),
            null,
            'info',
        );

        $pesan = "Promo terkirim ke {$sent} klien.";
        $pesan .= $failed ? " {$failed} gagal — lihat storage/logs/laravel.log." : '';

        return back()->with($failed ? 'error' : 'success', $pesan);
    }
}
