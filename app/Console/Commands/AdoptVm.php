<?php

namespace App\Console\Commands;

use App\Models\HostingAccount;
use App\Services\Hosting\IdCloudHostService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Hubungkan catatan VPS ke VM yang SUDAH ADA di provider.
 *
 * Dipakai saat pembuatan VM "timeout" di sisi aplikasi padahal VM-nya
 * sebenarnya berhasil dibuat -- catatan tertinggal berstatus pending
 * sementara VM sudah jalan (dan sudah menagih biaya ke akun provider).
 *
 * Sengaja lewat terminal, bukan hanya tombol di web: kalau tampilan
 * webnya bermasalah, pemulihan tetap bisa dilakukan.
 */
class AdoptVm extends Command
{
    protected $signature = 'lumora:adopt-vm {id? : ID hosting account. Kosongkan untuk melihat daftar kandidat}';

    protected $description = 'Hubungkan catatan VPS ke VM yang sudah ada di provider (pemulihan setelah timeout)';

    public function handle(): int
    {
        $id = $this->argument('id');

        if (! $id) {
            return $this->tampilkanKandidat();
        }

        $account = HostingAccount::with('serverModel')->find($id);

        if (! $account) {
            $this->error("Hosting account #{$id} tidak ditemukan.");

            return self::FAILURE;
        }

        if (! $account->serverModel || $account->serverModel->panel !== 'idcloudhost') {
            $this->error('Layanan ini bukan VPS IDCloudHost.');

            return self::FAILURE;
        }

        if ($account->provision_status === 'provisioned') {
            $this->warn("#{$account->id} {$account->domain} sudah berstatus provisioned — tidak perlu diadopsi.");

            return self::SUCCESS;
        }

        $this->info("Mencari VM bernama \"{$account->domain}\" di provider...");

        try {
            $service = new IdCloudHostService($account->serverModel);
            $result = $service->listVms();
        } catch (Throwable $e) {
            $this->error('Gagal menghubungi provider: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $result['success']) {
            $this->error('Provider menolak: ' . $result['message']);

            return self::FAILURE;
        }

        $cocok = collect($result['raw'] ?? [])->where('name', $account->domain)->values();

        if ($cocok->isEmpty()) {
            $this->error("Tidak ada VM bernama \"{$account->domain}\" di provider.");
            $this->line('VM yang ada di sana:');
            foreach (($result['raw'] ?? []) as $vm) {
                $this->line('  - ' . ($vm['name'] ?? '?') . ' (' . ($vm['status'] ?? '?') . ') uuid=' . ($vm['uuid'] ?? '?'));
            }

            return self::FAILURE;
        }

        if ($cocok->count() > 1) {
            $this->warn("PERHATIAN: ada {$cocok->count()} VM bernama sama — semuanya menagih biaya terpisah!");
            foreach ($cocok as $vm) {
                $this->line('  - uuid=' . ($vm['uuid'] ?? '?') . ' status=' . ($vm['status'] ?? '?'));
            }
            $this->line('Yang dipakai: yang pertama. Hapus sisanya lewat dashboard IDCloudHost.');
        }

        $vm = $cocok->first();

        $account->update([
            'username'          => $vm['uuid'] ?? $account->username,
            'status'            => ($vm['status'] ?? '') === 'running' ? 'active' : 'suspended',
            'provision_status'  => 'provisioned',
            'provision_message' => 'Diadopsi dari VM yang sudah ada di provider.',
            'last_billed_at'    => now(),
        ]);

        $this->newLine();
        $this->info('Berhasil dihubungkan ke VM ' . ($vm['uuid'] ?? '?'));
        $this->line('  Nama    : ' . ($vm['name'] ?? '?'));
        $this->line('  Status  : ' . ($vm['status'] ?? '?') . ' -> layanan jadi "' . $account->fresh()->status . '"');
        $this->line('  IP      : ' . ($vm['public_ipv4'] ?? $vm['private_ipv4'] ?? '-'));
        $this->line('  Spek    : ' . ($vm['vcpu'] ?? '?') . ' vCPU / ' . ($vm['memory'] ?? '?') . ' MB');
        $this->newLine();
        $this->line('Penagihan dihitung mulai SEKARANG (last_billed_at direset), jadi klien tidak');
        $this->line('ditagih untuk waktu sebelum VM ini terhubung ke sistem.');

        return self::SUCCESS;
    }

    private function tampilkanKandidat(): int
    {
        $kandidat = HostingAccount::with('serverModel')
            ->where('provision_status', '!=', 'provisioned')
            ->get()
            ->filter(fn ($a) => $a->serverModel && $a->serverModel->panel === 'idcloudhost');

        if ($kandidat->isEmpty()) {
            $this->info('Tidak ada VPS yang perlu diadopsi — semuanya sudah terhubung.');

            return self::SUCCESS;
        }

        $this->line('VPS yang belum terhubung ke VM di provider:');
        $this->newLine();

        foreach ($kandidat as $a) {
            $this->line("  #{$a->id}  {$a->domain}  (status: {$a->status}, provisioning: {$a->provision_status})");
        }

        $this->newLine();
        $this->line('Jalankan: php artisan lumora:adopt-vm <id>');

        return self::SUCCESS;
    }
}
