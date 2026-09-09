<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class InspectDomain extends Command
{
    protected $signature = 'lumora:inspect-domain {id?* : ID domain, kosongkan untuk lihat 10 terakhir}';

    protected $description = 'Lihat status provisioning domain langsung dari database, tanpa tinker.';

    public function handle(): int
    {
        $ids = $this->argument('id');

        $query = Domain::with(['client:id,name,email', 'registrar:id,name,provider', 'tld:id,extension'])
            ->orderByDesc('id');

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->limit(10);
        }

        $domains = $query->get();

        if ($domains->isEmpty()) {
            $this->warn('Tidak ada domain ditemukan.');

            return self::SUCCESS;
        }

        foreach ($domains as $d) {
            $this->newLine();
            $this->line("<fg=cyan>── ID {$d->id}: {$d->domain_name} ──</>");
            $this->line("Klien              : " . ($d->client->name ?? '—') . ' (' . ($d->client->email ?? '—') . ')');
            $this->line("Registrar          : " . ($d->registrar->name ?? '(tidak ada)') . ' [' . ($d->registrar->provider ?? '—') . ']');
            $this->line("TLD                : " . ($d->tld->extension ?? '(tidak terhubung TLD manapun — INI JANGGAL)'));
            $this->line("Harga tersimpan    : " . var_export($d->price, true));
            $this->line("Status             : {$d->status}");
            $this->line("Status Provisioning: {$d->provision_status}");
            $this->line("Pesan Provisioning : " . ($d->provision_message ?: '(kosong)'));
            $this->line("Transfer?          : " . ($d->is_transfer ? 'Ya' : 'Tidak'));
            $this->line("Tanggal Expiry     : " . ($d->expiry_date?->format('d M Y') ?? '(belum tercatat)'));
            $this->line("Nameserver         : " . (empty($d->nameservers) ? '(KOSONG)' : implode(', ', $d->nameservers)));
            $this->line("Dibuat             : {$d->created_at}");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
