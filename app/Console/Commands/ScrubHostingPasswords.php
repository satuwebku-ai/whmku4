<?php

namespace App\Console\Commands;

use App\Models\HostingAccount;
use Illuminate\Console\Command;

/**
 * Hapus baris "Password: ..." dari client_details akun hosting panel
 * (cPanel/DirectAdmin/Plesk) hasil provisioning otomatis lama. Akun VPS/
 * cloud tidak disentuh karena di sana client_details memang satu-satunya
 * tempat klien melihat kredensial.
 *
 * Default hanya simulasi; tambahkan --force untuk benar-benar menulis.
 */
class ScrubHostingPasswords extends Command
{
    protected $signature = 'hosting:scrub-passwords {--force : Benar-benar menghapus (tanpa ini hanya simulasi)}';

    protected $description = 'Hapus password tersimpan di client_details akun hosting panel (cPanel/DirectAdmin/Plesk).';

    public function handle(): int
    {
        $count = 0;

        HostingAccount::query()
            ->whereNotNull('client_details')
            ->whereHas('serverModel', fn ($q) => $q->whereIn('panel', ['cpanel', 'directadmin', 'plesk'])->whereNull('vps_provider'))
            ->each(function (HostingAccount $account) use (&$count) {
                $clean = preg_replace('/^\s*Password:.*\R?/mi', '', (string) $account->client_details);
                $clean = trim((string) $clean);

                if ($clean === trim((string) $account->client_details)) {
                    return;
                }

                $count++;
                $this->line("#{$account->id} {$account->domain}");

                if ($this->option('force')) {
                    $account->update(['client_details' => $clean !== '' ? $clean : null]);
                }
            });

        $this->info($this->option('force') ? "{$count} akun dibersihkan." : "{$count} akun akan dibersihkan. Jalankan dengan --force untuk menerapkan.");

        return self::SUCCESS;
    }
}
