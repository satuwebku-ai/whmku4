<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Console\Command;

class RestoreDeletedRecords extends Command
{
    protected $signature = 'records:restore {type : invoice, order, atau payment} {id? : ID record yang dipulihkan, kosongkan untuk melihat daftar yang terhapus}';

    protected $description = 'Lihat atau pulihkan invoice, order, dan payment yang dihapus dari admin (soft delete).';

    /** @var array<string, array{0: class-string, 1: string}> */
    private const TYPES = [
        'invoice' => [Invoice::class, 'invoice_number'],
        'order' => [Order::class, 'order_number'],
        'payment' => [Payment::class, 'reference'],
    ];

    public function handle(): int
    {
        $type = strtolower((string) $this->argument('type'));

        if (! isset(self::TYPES[$type])) {
            $this->error('Tipe tidak dikenal. Pilih: ' . implode(', ', array_keys(self::TYPES)) . '.');

            return self::FAILURE;
        }

        [$model, $numberColumn] = self::TYPES[$type];
        $id = $this->argument('id');

        if ($id === null) {
            $rows = $model::onlyTrashed()->orderByDesc('deleted_at')->limit(50)
                ->get(['id', $numberColumn, 'client_id', 'deleted_at']);

            if ($rows->isEmpty()) {
                $this->info("Tidak ada {$type} yang terhapus.");

                return self::SUCCESS;
            }

            $this->table(['ID', 'Nomor', 'Client ID', 'Dihapus pada'], $rows->map(fn ($r) => [
                $r->id, $r->{$numberColumn}, $r->client_id, $r->deleted_at,
            ])->all());

            return self::SUCCESS;
        }

        $record = $model::onlyTrashed()->find($id);

        if (! $record) {
            $this->error("Tidak ada {$type} terhapus dengan ID {$id}.");

            return self::FAILURE;
        }

        $record->restore();

        $this->info(ucfirst($type) . " {$record->{$numberColumn}} berhasil dipulihkan.");

        return self::SUCCESS;
    }
}
