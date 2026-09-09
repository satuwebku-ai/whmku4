<?php

namespace App\Console\Commands;

use App\Models\HostingAccount;
use Illuminate\Console\Command;

/**
 * Alat diagnosa cepat — dibuat khusus supaya TIDAK lewat tinker (yang
 * pakai eval(), dan eval() dimatikan di server ini demi keamanan).
 */
class InspectHostingAccount extends Command
{
    protected $signature = 'lumora:inspect-hosting {id?* : ID hosting account, kosongkan untuk lihat semua}';

    protected $description = 'Lihat status provisioning satu atau beberapa hosting account, tanpa perlu tinker.';

    public function handle(): int
    {
        $ids = $this->argument('id');

        $query = HostingAccount::with(['client:id,name,email', 'orders:id,hosting_account_id,order_type,status'])
            ->orderByDesc('id');

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->limit(10);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('Tidak ada hosting account ditemukan.');

            return self::SUCCESS;
        }

        foreach ($accounts as $acc) {
            $this->newLine();
            $this->line("<fg=cyan>── ID {$acc->id} ──</>");
            $this->line("Domain            : {$acc->domain}");
            $this->line("Klien             : " . ($acc->client->name ?? '—') . ' (' . ($acc->client->email ?? '—') . ')');
            $this->line("Status Akun       : {$acc->status}");
            $this->line("Status Provisioning: {$acc->provision_status}");
            $this->line("Pesan Provisioning: " . ($acc->provision_message ?: '(kosong)'));
            $this->line("Server ID         : " . ($acc->server_id ?? '(manual, tanpa server)'));
            $this->line("Username Panel    : " . ($acc->username ?: '(belum ada)'));
            $this->line("Order terkait     : " . ($acc->orders->isNotEmpty()
                ? $acc->orders->map(fn ($o) => "#{$o->id} ({$o->order_type}, status: {$o->status})")->implode(' | ')
                : 'TIDAK ADA — ini janggal, hosting account seharusnya selalu berasal dari order'));
            $this->line("Dibuat            : {$acc->created_at}");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
