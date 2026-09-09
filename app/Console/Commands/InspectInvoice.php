<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class InspectInvoice extends Command
{
    protected $signature = 'lumora:inspect-invoice {id?* : ID invoice, kosongkan untuk lihat 10 terakhir}';

    protected $description = 'Lihat nilai amount/tax/discount/total sebuah invoice langsung dari database, tanpa tinker.';

    public function handle(): int
    {
        $ids = $this->argument('id');

        $query = Invoice::with('items:id,invoice_id,order_id,description,amount')->orderByDesc('id');

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->limit(10);
        }

        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            $this->warn('Tidak ada invoice ditemukan.');

            return self::SUCCESS;
        }

        foreach ($invoices as $inv) {
            $this->newLine();
            $this->line("<fg=cyan>── {$inv->invoice_number} (ID {$inv->id}) ──</>");
            $this->line("Status    : {$inv->status}");
            $this->line("amount    : " . var_export($inv->amount, true));
            $this->line("tax       : " . var_export($inv->tax, true));
            $this->line("discount  : " . var_export($inv->discount, true));
            $this->line("total     : " . var_export($inv->total, true));
            $this->line("Item invoice:");

            if ($inv->items->isEmpty()) {
                $this->line("  (TIDAK ADA item sama sekali — ini janggal)");
            }

            foreach ($inv->items as $item) {
                $this->line("  - {$item->description}: " . var_export($item->amount, true) . " (order_id: " . var_export($item->order_id, true) . ")");
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
