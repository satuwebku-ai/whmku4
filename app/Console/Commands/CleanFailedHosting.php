<?php

namespace App\Console\Commands;

use App\Models\HostingAccount;
use Illuminate\Console\Command;

/**
 * Bersihkan Hosting Account yang gagal diprovisikan (belum pernah benar-
 * benar ada di server manapun) — aman dihapus tanpa risiko meninggalkan
 * akun cPanel sungguhan yang menggantung, karena akunnya memang tidak
 * pernah benar-benar dibuat di WHM.
 */
class CleanFailedHosting extends Command
{
    protected $signature = 'lumora:clean-failed-hosting
                            {--domain= : Cuma hapus yang domainnya cocok (opsional)}
                            {--dry : Cuma tampilkan yang AKAN dihapus, tanpa benar-benar menghapus}';

    protected $description = 'Hapus Hosting Account berstatus gagal/pending yang tidak pernah benar-benar dibuat di server.';

    public function handle(): int
    {
        // provision_status = 'failed' adalah penanda paling bisa
        // diandalkan — itu SENGAJA cuma diset kalau WHM benar-benar
        // menolak permintaan createacct (lihat ProvisioningService).
        // Kolom username TIDAK bisa dipakai sebagai penanda "belum
        // pernah dicoba", karena ternyata tetap terisi (nilai yang
        // sudah dibuat lokal) walau permintaan ke WHM-nya gagal.
        $query = HostingAccount::where('provision_status', 'failed');

        if ($domain = $this->option('domain')) {
            $query->where('domain', $domain);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('Tidak ada yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$accounts->count()} hosting account yang gagal/belum pernah diprovisikan:");

        foreach ($accounts as $acc) {
            $this->line("  - ID {$acc->id}: {$acc->domain} (status: {$acc->provision_status})");
        }

        if ($this->option('dry')) {
            $this->newLine();
            $this->info('Mode simulasi — tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Hapus semua yang tercantum di atas?', true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $count = $accounts->count();
        HostingAccount::whereIn('id', $accounts->pluck('id'))->delete();

        $this->info("Selesai — {$count} hosting account dihapus.");

        return self::SUCCESS;
    }
}
