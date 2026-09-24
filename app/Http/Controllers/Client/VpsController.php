<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\HostingAccount;
use App\Models\Server;
use App\Services\Billing\HourlyRateCalculator;
use App\Services\Vps\VpsProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Area VPS untuk klien -- terpisah dari ServiceController (hosting
 * cPanel) karena yang dikelola beda total: mesin virtual dengan
 * status hidup/mati, bukan akun di dalam server. Klien bisa
 * menyalakan/mematikan sendiri, dan melihat sisa saldunya berbanding
 * tarif per jam.
 */
class VpsController extends Controller
{
    use AuthorizesClientOwnership;

    public function index(): View
    {
        $client = Auth::guard('client')->user();

        $accounts = HostingAccount::where('client_id', $client->id)
            ->whereIn('server_id', Server::cloud()->pluck('id'))
            ->with('serverModel')
            ->latest()
            ->get();

        $rates = [];
        foreach ($accounts as $account) {
            $rates[$account->id] = $this->rateFor($account);
        }

        return view('client.vps.index', compact('accounts', 'rates', 'client'));
    }

    public function show(HostingAccount $vps): View|RedirectResponse
    {
        $this->authorizeOwner($vps);
        $this->ensureIsVps($vps);

        // Status VM diambil langsung dari provider (real-time), bukan
        // dari catatan database yang bisa ketinggalan -- klien perlu
        // tahu kondisi SEBENARNYA mesinnya.
        $vmInfo = null;
        $apiError = null;
        $osImages = [];

        if ($vps->serverModel && $vps->username) {
            try {
                $service = VpsProviderFactory::make($vps->serverModel);

                $result = $service->get($vps->username);
                $vmInfo = $result['success'] ? $result['raw'] : null;
                $apiError = $result['success'] ? null : $result['message'];

                $img = $service->images();
                $osImages = $img['success'] ? ($img['raw'] ?? []) : [];
            } catch (Throwable $e) {
                $apiError = $e->getMessage();
            }
        }

        $client = Auth::guard('client')->user();
        $rate = $this->rateFor($vps);
        $breakdown = ($vps->serverModel && $vps->hasVmSpec())
            ? HourlyRateCalculator::breakdown($vps->serverModel, $vps->vmSpec(), $vps->product)
            : [];
        $hoursLeft = ($rate && $rate > 0) ? floor((float) $client->balance / $rate) : null;

        return view('client.vps.show', compact('vps', 'vmInfo', 'apiError', 'rate', 'breakdown', 'hoursLeft', 'client', 'osImages'));
    }

    public function power(Request $request, HostingAccount $vps): RedirectResponse
    {
        $this->authorizeOwner($vps);
        $this->ensureIsVps($vps);

        $action = $request->validate(['action' => ['required', 'in:start,stop,restart,force_stop']])['action'];

        $result = Cache::lock("vps-operation:{$vps->id}", 180)->get(function () use ($vps, $action) {
            $current = $vps->fresh(['serverModel', 'product']);

            if (! $current || ! $current->serverModel || ! $current->username) {
                return ['success' => false, 'message' => 'VM ini belum terhubung ke provider.'];
            }

            // Menyalakan VM saat saldo sudah habis akan langsung dimatikan
            // lagi oleh cron penagihan -- cek ini di dalam lock agar dua
            // request paralel tidak sama-sama lolos dari pemeriksaan.
            if ($action === 'start' && $current->billing_mode === 'deposit') {
                $rate = $this->rateFor($current);

                if ($rate > 0 && (float) Auth::guard('client')->user()->balance < $rate) {
                    return ['success' => false, 'message' => 'Saldo Anda tidak cukup untuk menjalankan VPS ini. Silakan isi ulang saldo dulu.'];
                }
            }

            try {
                $service = VpsProviderFactory::make($current->serverModel);

                $providerResult = match ($action) {
                    'start'      => $service->start($current->username),
                    'stop'       => $service->stop($current->username),
                    'force_stop' => $service->stop($current->username, true),
                    'restart'    => $service->restart($current->username),
                };
            } catch (Throwable $e) {
                Log::warning("Aksi VPS {$action} gagal untuk #{$current->id}: " . $e->getMessage());

                return ['success' => false, 'message' => 'Perintah gagal dikirim: ' . $e->getMessage()];
            }

            if (! $providerResult['success']) {
                return ['success' => false, 'message' => 'Provider menolak perintah: ' . $providerResult['message']];
            }

            // Status lokal disesuaikan sebelum lock dilepas supaya request
            // berikutnya tidak berangkat dari state yang sudah basi.
            if (in_array($action, ['stop', 'force_stop'], true)) {
                $current->update(['status' => 'suspended']);
            } elseif ($action === 'start') {
                $current->update(['status' => 'active', 'last_billed_at' => now()]);
            }

            return ['success' => true];
        });

        if (! is_array($result)) {
            return back()->with('error', 'Aksi VPS lain sedang diproses. Silakan coba lagi sebentar.');
        }

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', match ($action) {
            'start'      => 'VPS sedang dinyalakan. Tunggu sekitar satu menit.',
            'stop'       => 'VPS sedang dimatikan.',
            'force_stop' => 'VPS dimatikan paksa.',
            'restart'    => 'VPS sedang dinyalakan ulang.',
        });
    }

    public function changePassword(Request $request, HostingAccount $vps): RedirectResponse
    {
        $this->authorizeOwner($vps);
        $this->ensureIsVps($vps);

        $data = $request->validate([
            'vm_username' => ['required', 'string', 'max:50'],
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $result = Cache::lock("vps-operation:{$vps->id}", 180)->get(function () use ($vps, $data) {
            $current = $vps->fresh('serverModel');

            if (! $current || ! $current->serverModel || ! $current->username) {
                return ['success' => false, 'message' => 'VM ini belum terhubung ke provider.'];
            }

            try {
                $providerResult = VpsProviderFactory::make($current->serverModel)
                    ->changePassword($current->username, $data['vm_username'], $data['new_password']);
            } catch (Throwable $e) {
                return ['success' => false, 'message' => 'Gagal mengganti password: ' . $e->getMessage()];
            }

            return $providerResult['success']
                ? ['success' => true]
                : ['success' => false, 'message' => 'Provider menolak: ' . $providerResult['message']
                    . ' (password hanya bisa diganti saat VM menyala).'];
        });

        if (! is_array($result)) {
            return back()->with('error', 'Aksi VPS lain sedang diproses. Silakan coba lagi sebentar.');
        }

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Password VPS berhasil diganti.');
    }

    /**
     * Instal ulang OS. SELURUH DATA di disk utama HILANG -- karena itu
     * klien wajib mengetik nama VPS-nya sebagai konfirmasi, bukan
     * sekadar klik tombol.
     */
    public function reinstall(Request $request, HostingAccount $vps): RedirectResponse
    {
        $this->authorizeOwner($vps);
        $this->ensureIsVps($vps);

        $data = $request->validate([
            'konfirmasi' => ['required', 'string'],
            'os_name'    => ['required', 'string', 'max:50'],
            'os_version' => ['required', 'string', 'max:50'],
        ]);

        if (trim($data['konfirmasi']) !== $vps->domain) {
            return back()->with('error', 'Konfirmasi tidak cocok — ketik nama VPS persis untuk melanjutkan.');
        }

        $result = Cache::lock("vps-operation:{$vps->id}", 180)->get(function () use ($vps, $data) {
            $current = $vps->fresh('serverModel');

            if (! $current || ! $current->serverModel || ! $current->username) {
                return ['success' => false, 'message' => 'VM ini belum terhubung ke provider.'];
            }

            try {
                $providerResult = VpsProviderFactory::make($current->serverModel)
                    ->reinstall($current->username, $data['os_name'], $data['os_version']);
            } catch (Throwable $e) {
                return ['success' => false, 'message' => 'Gagal instal ulang: ' . $e->getMessage()];
            }

            if (! $providerResult['success']) {
                return ['success' => false, 'message' => 'Provider menolak: ' . $providerResult['message']];
            }

            $spec = $current->hasVmSpec() ? $current->vmSpec() : [];
            $spec['os_name'] = $data['os_name'];
            $spec['os_version'] = $data['os_version'];
            $current->update(['package' => json_encode($spec)]);

            return ['success' => true];
        });

        if (! is_array($result)) {
            return back()->with('error', 'Aksi VPS lain sedang diproses. Silakan coba lagi sebentar.');
        }

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'VPS sedang diinstal ulang. Proses ini bisa memakan beberapa menit.');
    }

    /**
     * Ubah vCPU/RAM. Provider hanya mengizinkan saat VM MATI, dan
     * perubahan ini langsung mengubah tarif per jam -- jadi klien
     * diberi tahu tarif barunya sebelum menyetujui.
     */
    public function resize(Request $request, HostingAccount $vps): RedirectResponse
    {
        $this->authorizeOwner($vps);
        $this->ensureIsVps($vps);

        $data = $request->validate([
            'vcpu' => ['required', 'integer', 'min:1', 'max:32'],
            'ram'  => ['required', 'integer', 'min:512'],
        ]);

        if (! $vps->serverModel || ! $vps->username) {
            return back()->with('error', 'VM ini belum terhubung ke provider.');
        }

        if ($vps->status === 'active') {
            return back()->with('error', 'Matikan VPS dulu sebelum mengubah spesifikasi — provider tidak mengizinkan perubahan saat VM menyala.');
        }

        $spec = $vps->hasVmSpec() ? $vps->vmSpec() : [];
        $spec['vcpu'] = $data['vcpu'];
        $spec['ram'] = $data['ram'];

        // Tarif baru dihitung dulu supaya bisa dicek terhadap saldo --
        // percuma menaikkan spek kalau saldonya tidak cukup untuk
        // sejam pun.
        $tarifBaru = $vps->serverModel
            ? HourlyRateCalculator::calculate($vps->serverModel, $spec, $vps->product)
            : 0;

        $saldo = (float) Auth::guard('client')->user()->balance;

        if ($vps->billing_mode === 'deposit' && $tarifBaru > 0 && $saldo < $tarifBaru) {
            return back()->with('error', 'Saldo tidak cukup untuk tarif spesifikasi baru (Rp '
                . number_format($tarifBaru, 2, ',', '.') . '/jam). Isi saldo dulu.');
        }

        $result = Cache::lock("vps-operation:{$vps->id}", 180)->get(function () use ($vps, $spec) {
            $current = $vps->fresh('serverModel');

            if (! $current || ! $current->serverModel || ! $current->username) {
                return ['success' => false, 'message' => 'VM ini belum terhubung ke provider.'];
            }

            if ($current->status === 'active') {
                return ['success' => false, 'message' => 'Matikan VPS dulu sebelum mengubah spesifikasi — provider tidak mengizinkan perubahan saat VM menyala.'];
            }

            try {
                $providerResult = VpsProviderFactory::make($current->serverModel)
                    ->resize($current->username, $spec);
            } catch (Throwable $e) {
                return ['success' => false, 'message' => 'Gagal mengubah spesifikasi: ' . $e->getMessage()];
            }

            if (! $providerResult['success']) {
                return ['success' => false, 'message' => 'Provider menolak: ' . $providerResult['message']];
            }

            $current->update(['package' => json_encode($spec)]);

            return ['success' => true];
        });

        if (! is_array($result)) {
            return back()->with('error', 'Aksi VPS lain sedang diproses. Silakan coba lagi sebentar.');
        }

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Spesifikasi berhasil diubah. Tarif baru: Rp '
            . number_format($tarifBaru, 2, ',', '.') . '/jam. Nyalakan VPS untuk memakainya.');
    }

    /**
     * Restart = stop lalu start. IDCloudHost tidak punya endpoint
     * restart tersendiri, jadi dilakukan berurutan.
     */
        private function rateFor(HostingAccount $account): ?float
    {
        return HourlyRateCalculator::forAccount($account);
    }

    private function ensureIsVps(HostingAccount $vps): void
    {
        // HostingAccountPolicy (dipakai authorizeOwner() dari trait) cuma
        // cek kepemilikan -- sama untuk hosting cPanel biasa maupun VPS.
        // Cek tambahan ini KHUSUS memastikan akun ini benar VPS (server
        // bertipe VM/VPS), supaya klien tidak bisa memicu aksi
        // power/reinstall/resize di ID hosting_account miliknya sendiri
        // yang sebetulnya akun hosting cPanel biasa.
        abort_unless($vps->serverModel?->isCloud(), 404);
    }
}
