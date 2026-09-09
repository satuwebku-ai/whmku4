<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\HostingAccount;
use App\Models\Server;
use App\Services\Billing\HourlyRateCalculator;
use App\Services\Hosting\IdCloudHostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function index(): View
    {
        $client = Auth::guard('client')->user();

        $accounts = HostingAccount::where('client_id', $client->id)
            ->whereIn('server_id', Server::whereIn('panel', ['idcloudhost'])->pluck('id'))
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

        // Status VM diambil langsung dari provider (real-time), bukan
        // dari catatan database yang bisa ketinggalan -- klien perlu
        // tahu kondisi SEBENARNYA mesinnya.
        $vmInfo = null;
        $apiError = null;
        $osImages = [];

        if ($vps->serverModel && $vps->username) {
            try {
                $service = new IdCloudHostService($vps->serverModel);

                $result = $service->getVmInfo($vps->username);
                $vmInfo = $result['success'] ? $result['raw'] : null;
                $apiError = $result['success'] ? null : $result['message'];

                $img = $service->listVmImages();
                $osImages = $img['success'] ? ($img['raw'] ?? []) : [];
            } catch (Throwable $e) {
                $apiError = $e->getMessage();
            }
        }

        $client = Auth::guard('client')->user();
        $rate = $this->rateFor($vps);
        $hoursLeft = ($rate && $rate > 0) ? floor((float) $client->balance / $rate) : null;

        return view('client.vps.show', compact('vps', 'vmInfo', 'apiError', 'rate', 'hoursLeft', 'client', 'osImages'));
    }

    public function power(Request $request, HostingAccount $vps): RedirectResponse
    {
        $this->authorizeOwner($vps);

        $action = $request->validate(['action' => ['required', 'in:start,stop,restart,force_stop']])['action'];

        if (! $vps->serverModel || ! $vps->username) {
            return back()->with('error', 'VM ini belum terhubung ke provider.');
        }

        // Menyalakan VM saat saldo sudah habis akan langsung dimatikan
        // lagi oleh cron penagihan -- lebih jujur menolaknya di sini
        // dengan penjelasan, daripada membiarkan klien bingung.
        if ($action === 'start' && $vps->billing_mode === 'deposit') {
            $rate = $this->rateFor($vps);

            if ($rate > 0 && (float) Auth::guard('client')->user()->balance < $rate) {
                return back()->with('error', 'Saldo Anda tidak cukup untuk menjalankan VPS ini. Silakan isi ulang saldo dulu.');
            }
        }

        try {
            $service = new IdCloudHostService($vps->serverModel);

            $result = match ($action) {
                'start'      => $service->unsuspendAccount($vps->username),
                'stop'       => $service->suspendAccount($vps->username),
                'force_stop' => $service->forceStop($vps->username),
                'restart'    => $this->restart($service, $vps->username),
            };
        } catch (Throwable $e) {
            Log::warning("Aksi VPS {$action} gagal untuk #{$vps->id}: " . $e->getMessage());

            return back()->with('error', 'Perintah gagal dikirim: ' . $e->getMessage());
        }

        if (! $result['success']) {
            return back()->with('error', 'Provider menolak perintah: ' . $result['message']);
        }

        // Status lokal disesuaikan supaya daftar layanan tidak
        // menampilkan info basi sampai halaman dimuat ulang.
        if (in_array($action, ['stop', 'force_stop'], true)) {
            $vps->update(['status' => 'suspended']);
        } elseif ($action === 'start') {
            $vps->update(['status' => 'active', 'last_billed_at' => now()]);
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

        $data = $request->validate([
            'vm_username' => ['required', 'string', 'max:50'],
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        if (! $vps->serverModel || ! $vps->username) {
            return back()->with('error', 'VM ini belum terhubung ke provider.');
        }

        try {
            $result = (new IdCloudHostService($vps->serverModel))
                ->changePassword($vps->username, $data['vm_username'], $data['new_password']);
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal mengganti password: ' . $e->getMessage());
        }

        if (! $result['success']) {
            return back()->with('error', 'Provider menolak: ' . $result['message']
                . ' (password hanya bisa diganti saat VM menyala).');
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

        $data = $request->validate([
            'konfirmasi' => ['required', 'string'],
            'os_name'    => ['required', 'string', 'max:50'],
            'os_version' => ['required', 'string', 'max:50'],
        ]);

        if (trim($data['konfirmasi']) !== $vps->domain) {
            return back()->with('error', 'Konfirmasi tidak cocok — ketik nama VPS persis untuk melanjutkan.');
        }

        if (! $vps->serverModel || ! $vps->username) {
            return back()->with('error', 'VM ini belum terhubung ke provider.');
        }

        try {
            $result = (new IdCloudHostService($vps->serverModel))
                ->reinstall($vps->username, $data['os_name'], $data['os_version']);
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal instal ulang: ' . $e->getMessage());
        }

        if (! $result['success']) {
            return back()->with('error', 'Provider menolak: ' . $result['message']);
        }

        // Spek OS di catatan ikut diperbarui supaya tagihan (yang
        // memperhitungkan lisensi Windows) tetap sesuai kenyataan.
        $spec = $vps->hasVmSpec() ? $vps->vmSpec() : [];
        $spec['os_name'] = $data['os_name'];
        $spec['os_version'] = $data['os_version'];
        $vps->update(['package' => json_encode($spec)]);

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
            ? HourlyRateCalculator::calculate($vps->serverModel, $spec)
            : 0;

        $saldo = (float) Auth::guard('client')->user()->balance;

        if ($vps->billing_mode === 'deposit' && $tarifBaru > 0 && $saldo < $tarifBaru) {
            return back()->with('error', 'Saldo tidak cukup untuk tarif spesifikasi baru (Rp '
                . number_format($tarifBaru, 2, ',', '.') . '/jam). Isi saldo dulu.');
        }

        try {
            $result = (new IdCloudHostService($vps->serverModel))
                ->changePackage($vps->username, json_encode($spec));
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal mengubah spesifikasi: ' . $e->getMessage());
        }

        if (! $result['success']) {
            return back()->with('error', 'Provider menolak: ' . $result['message']);
        }

        $vps->update(['package' => json_encode($spec)]);

        return back()->with('success', 'Spesifikasi berhasil diubah. Tarif baru: Rp '
            . number_format($tarifBaru, 2, ',', '.') . '/jam. Nyalakan VPS untuk memakainya.');
    }

    /**
     * Restart = stop lalu start. IDCloudHost tidak punya endpoint
     * restart tersendiri, jadi dilakukan berurutan.
     */
    private function restart(IdCloudHostService $service, string $uuid): array
    {
        $stop = $service->suspendAccount($uuid);

        if (! $stop['success']) {
            return $stop;
        }

        sleep(3);

        return $service->unsuspendAccount($uuid);
    }

    private function rateFor(HostingAccount $account): ?float
    {
        if ($account->serverModel && $account->hasVmSpec()) {
            $rate = HourlyRateCalculator::calculate($account->serverModel, $account->vmSpec());

            if ($rate > 0) {
                return $rate;
            }
        }

        return $account->hourly_rate ? (float) $account->hourly_rate : null;
    }

    private function authorizeOwner(HostingAccount $vps): void
    {
        abort_unless($vps->client_id === Auth::guard('client')->id(), 403);
    }
}
