<?php

namespace App\Services\Billing;

use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengisi invoice_items.service_type / service_id / item_type untuk invoice
 * lama berdasarkan teks deskripsi dan order_id. Aman dijalankan berulang:
 * hanya menyentuh baris yang belum punya rujukan, dan melewati yang
 * ambigu (lebih dari satu layanan cocok) alih-alih menebak.
 */
class InvoiceItemServiceBackfill
{
    /** Pola deskripsi -> [jenis layanan, jenis item]. {D} = tanda pisah (— – -). */
    private const PATTERNS = [
        ['/^(?:Perpanjangan\s+)?ID Protection\s+{D}\s+(\S+)/iu', InvoiceItem::SERVICE_DOMAIN, InvoiceItem::TYPE_PRIVACY],
        ['/^Perpanjangan Domain\s+{D}\s+(\S+)/iu', InvoiceItem::SERVICE_DOMAIN, InvoiceItem::TYPE_RENEWAL],
        ['/^Perpanjangan Hosting\s+{D}\s+(\S+)/iu', InvoiceItem::SERVICE_HOSTING, InvoiceItem::TYPE_RENEWAL],
        ['/^Upgrade\s+([^\s:]+):/iu', InvoiceItem::SERVICE_HOSTING, InvoiceItem::TYPE_UPGRADE],
        ['/^Addon\s+.+?\s+{D}\s+(\S+)\s+\(prorata/iu', InvoiceItem::SERVICE_HOSTING, InvoiceItem::TYPE_ADDON],
        ['/^Addon\s+{D}\s+.+\(([^\s,()]+),/iu', InvoiceItem::SERVICE_HOSTING, InvoiceItem::TYPE_ADDON_RENEWAL],
    ];

    /**
     * @return array{service_type: string, item_type: string, name: string}|null
     */
    public static function parse(string $description): ?array
    {
        foreach (self::PATTERNS as [$pattern, $serviceType, $itemType]) {
            $regex = str_replace('{D}', '[\x{2014}\x{2013}-]', $pattern);

            if (preg_match($regex, trim($description), $m)) {
                return ['service_type' => $serviceType, 'item_type' => $itemType, 'name' => $m[1]];
            }
        }

        return null;
    }

    /**
     * @return array<string, int|string>
     */
    public function run(bool $dry = false): array
    {
        $stats = ['matched' => 0, 'by_order' => 0, 'ambiguous' => 0, 'not_found' => 0, 'unparsed' => 0];

        if (! Schema::hasTable('invoice_items') || ! Schema::hasColumn('invoice_items', 'service_type')) {
            return $stats + ['error' => 'Kolom service_type belum ada — jalankan php artisan migrate.'];
        }

        DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNull('invoice_items.service_type')
            ->select('invoice_items.id', 'invoice_items.description', 'invoice_items.order_id', 'invoices.client_id')
            ->chunkById(200, function ($rows) use (&$stats, $dry) {
                foreach ($rows as $row) {
                    $this->resolveRow($row, $stats, $dry);
                }
            }, 'invoice_items.id', 'id');

        return $stats;
    }

    private function resolveRow(object $row, array &$stats, bool $dry): void
    {
        $parsed = self::parse((string) $row->description);

        if ($parsed) {
            [$table, $column] = $parsed['service_type'] === InvoiceItem::SERVICE_DOMAIN
                ? ['domains', 'domain_name']
                : ['hosting_accounts', 'domain'];

            $ids = DB::table($table)
                ->where('client_id', $row->client_id)
                ->whereRaw('LOWER(' . $column . ') = ?', [strtolower($parsed['name'])])
                ->pluck('id');

            $this->apply($row->id, $parsed['service_type'], $ids, $parsed['item_type'], $stats, 'matched', $dry);

            return;
        }

        // Pembelian awal: hanya domains yang menyimpan order_id.
        if ($row->order_id && Schema::hasColumn('domains', 'order_id')) {
            $ids = DB::table('domains')->where('order_id', $row->order_id)->pluck('id');

            if ($ids->isNotEmpty()) {
                $this->apply($row->id, InvoiceItem::SERVICE_DOMAIN, $ids, InvoiceItem::TYPE_ORDER, $stats, 'by_order', $dry);

                return;
            }
        }

        $stats['unparsed']++;
    }

    private function apply(int $itemId, string $serviceType, $ids, string $itemType, array &$stats, string $counter, bool $dry): void
    {
        if ($ids->count() > 1) {
            $stats['ambiguous']++;

            return;
        }

        if ($ids->isEmpty()) {
            $stats['not_found']++;

            return;
        }

        if (! $dry) {
            DB::table('invoice_items')->where('id', $itemId)->update([
                'service_type' => $serviceType,
                'service_id' => (int) $ids->first(),
                'item_type' => $itemType,
            ]);
        }

        $stats[$counter]++;
    }
}
