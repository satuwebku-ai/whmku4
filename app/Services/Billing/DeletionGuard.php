<?php

namespace App\Services\Billing;

use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\ClientBalanceLog;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tld;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan kapan sebuah record BOLEH dihapus dari panel admin.
 *
 * Foreign key di database memakai cascade/null, jadi `->delete()` polos
 * diam-diam menghapus atau memutus tabel lain (pembayaran, item invoice,
 * tautan renewal, dst.) tanpa menjalankan alur bisnis apa pun. Semua method
 * di sini mengembalikan string alasan penolakan (siap ditampilkan ke admin)
 * atau null kalau aman dihapus. Method ini tidak mengubah data.
 */
class DeletionGuard
{
    /** Invoice yang masih bisa dibayar klien. */
    private const OPEN_INVOICE = ['unpaid', 'overdue'];

    /** Invoice yang sudah menjadi catatan uang masuk/keluar. */
    private const MONEY_INVOICE = ['paid', 'refunded'];

    public function forInvoice(Invoice $invoice): ?string
    {
        if (in_array($invoice->status, self::MONEY_INVOICE, true)) {
            return "Invoice {$invoice->invoice_number} berstatus {$invoice->status}. Ini catatan keuangan dan tidak boleh dihapus.";
        }

        if (in_array($invoice->status, self::OPEN_INVOICE, true)) {
            return "Invoice {$invoice->invoice_number} masih {$invoice->status}. Batalkan dulu lewat tombol Cancel supaya kupon, stok, dan tautan perpanjangan ikut dilepas, baru hapus.";
        }

        if ($invoice->payments()->whereIn('status', ['paid', 'refunded'])->exists()
            || $invoice->transactions()->exists()) {
            return "Invoice {$invoice->invoice_number} punya catatan pembayaran atau transaksi keuangan, jadi tidak boleh dihapus.";
        }

        return null;
    }

    public function forOrder(Order $order): ?string
    {
        $status = $this->orderStatus($order);

        if (in_array($status, [OrderStatus::Paid, OrderStatus::Provisioning, OrderStatus::Completed], true)) {
            return "Order {$order->order_number} berstatus {$status->value}. Layanannya sudah dibayar atau diproses, jadi tidak boleh dihapus.";
        }

        $invoices = $this->invoicesOfOrder($order);

        $money = $invoices->first(fn (Invoice $i) => in_array($i->status, self::MONEY_INVOICE, true));
        if ($money) {
            return "Order {$order->order_number} terkait invoice {$money->invoice_number} yang berstatus {$money->status}, jadi tidak boleh dihapus.";
        }

        $open = $invoices->first(fn (Invoice $i) => in_array($i->status, self::OPEN_INVOICE, true));
        if ($open) {
            return "Order {$order->order_number} masih punya invoice {$open->invoice_number} yang {$open->status}. Batalkan invoice itu dulu, baru hapus order.";
        }

        if ($order->stock_reservation_status === 'reserved') {
            return "Order {$order->order_number} masih menahan stok produk. Batalkan invoice terkait dulu supaya stok dilepas.";
        }

        $domain = Domain::query()->where('order_id', $order->id)->first();
        if ($domain && $this->domainIsLive($domain)) {
            return "Order {$order->order_number} punya domain {$domain->domain_name} yang masih aktif di registrar.";
        }

        $hosting = HostingAccount::query()->where('id', $order->hosting_account_id)->first();
        if ($hosting && $this->hostingIsLive($hosting)) {
            return "Order {$order->order_number} punya layanan {$hosting->domain} yang masih aktif di server.";
        }

        return null;
    }

    public function forPayment(Payment $payment): ?string
    {
        if (in_array($payment->status, ['paid', 'refunded'], true)) {
            return "Pembayaran {$payment->reference} berstatus {$payment->status}. Ini bukti uang masuk dan tidak boleh dihapus.";
        }

        if (in_array($payment->status, ['initiated', 'pending'], true)) {
            return "Pembayaran {$payment->reference} masih menunggu ({$payment->status}) dan bisa saja masih menerima webhook dari gateway. Batalkan invoice-nya atau tunggu sampai kedaluwarsa.";
        }

        return null;
    }

    public function forDomain(Domain $domain): ?string
    {
        if ($this->domainIsLive($domain)) {
            return "Domain {$domain->domain_name} masih aktif atau sedang diproses di registrar. Menghapus data di sini tidak membatalkan pendaftarannya. Ubah statusnya dulu (Cancelled atau Expired).";
        }

        foreach (['renewal_invoice_id' => 'perpanjangan', 'privacy_invoice_id' => 'ID Protection'] as $column => $label) {
            $open = $this->openInvoice($domain->{$column});
            if ($open) {
                return "Domain {$domain->domain_name} masih punya invoice {$label} {$open->invoice_number} yang {$open->status}. Batalkan invoice itu dulu.";
            }
        }

        return null;
    }

    /**
     * @param bool $terminatingNow true kalau aksi ini sendiri yang akan
     *        menghapus VM/akun di provider (opsi hapus_vm di halaman VPS),
     *        jadi status "aktif" tidak lagi jadi alasan menolak.
     */
    public function forHosting(HostingAccount $account, bool $terminatingNow = false): ?string
    {
        if (! $terminatingNow && $this->hostingIsLive($account)) {
            return "Layanan {$account->domain} masih {$account->status} di server. Menghapus data di sini tidak menghapus akunnya di panel. Terminate dulu (status terminated).";
        }

        foreach (['renewal_invoice_id' => 'perpanjangan', 'pending_upgrade_invoice_id' => 'upgrade'] as $column => $label) {
            $open = $this->openInvoice($account->{$column});
            if ($open) {
                return "Layanan {$account->domain} masih punya invoice {$label} {$open->invoice_number} yang {$open->status}. Batalkan invoice itu dulu.";
            }
        }

        return null;
    }

    public function forClient(Client $client): ?string
    {
        $blockers = [];

        $checks = [
            'invoice' => [Invoice::class, 'client_id'],
            'order' => [Order::class, 'client_id'],
            'pembayaran' => [Payment::class, 'client_id'],
            'domain' => [Domain::class, 'client_id'],
            'hosting/VPS' => [HostingAccount::class, 'client_id'],
            'riwayat saldo' => [ClientBalanceLog::class, 'client_id'],
            'akun afiliasi' => [Affiliate::class, 'client_id'],
        ];

        foreach ($checks as $label => [$model, $column]) {
            if ($this->exists($model, $column, $client->id)) {
                $blockers[] = $label;
            }
        }

        if ((float) $client->balance !== 0.0 && ! in_array('riwayat saldo', $blockers, true)) {
            $blockers[] = 'saldo';
        }

        if ($blockers === []) {
            return null;
        }

        return 'Klien ' . $client->name . ' tidak bisa dihapus karena masih punya ' . implode(', ', $blockers)
            . '. Menghapus klien ikut menghapus semuanya. Nonaktifkan klien (status inactive) supaya data keuangan tetap utuh.';
    }

    public function forProduct(Product $product): ?string
    {
        $used = $this->exists(Order::class, 'product_id', $product->id)
            || $this->exists(HostingAccount::class, 'product_id', $product->id)
            || $this->exists(HostingAccount::class, 'pending_upgrade_product_id', $product->id);

        return $used
            ? "Produk {$product->name} sudah dipakai order atau layanan klien. Nonaktifkan saja supaya tidak bisa dipesan baru."
            : null;
    }

    public function forTld(Tld $tld): ?string
    {
        return $this->exists(Domain::class, 'tld_id', $tld->id)
            ? "TLD {$tld->extension} masih dipakai domain klien. Nonaktifkan saja supaya tidak bisa dipesan baru."
            : null;
    }

    /**
     * Domain "hidup" = terdaftar atau sedang diproses di registrar.
     */
    private function domainIsLive(Domain $domain): bool
    {
        if ($domain->status === 'active') {
            return true;
        }

        return $domain->status === 'pending'
            && in_array($domain->provision_status, ['registered', 'transfer_pending', 'provisioning'], true);
    }

    /**
     * Hosting/VPS "hidup" = akunnya (masih) ada di panel atau provider.
     */
    private function hostingIsLive(HostingAccount $account): bool
    {
        if (in_array($account->status, ['active', 'suspended'], true)) {
            return true;
        }

        return $account->status !== 'terminated' && $account->provision_status === 'provisioned';
    }

    private function orderStatus(Order $order): ?OrderStatus
    {
        return $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);
    }

    /**
     * Semua invoice yang terkait order, lewat invoices.order_id (invoice lama)
     * maupun invoice_items.order_id (checkout multi-item).
     *
     * @return Collection<int, Invoice>
     */
    private function invoicesOfOrder(Order $order): Collection
    {
        return Invoice::query()
            ->where(function ($q) use ($order) {
                $q->where('order_id', $order->id)
                    ->orWhereIn('id', InvoiceItem::query()->where('order_id', $order->id)->select('invoice_id'));
            })
            ->get(['id', 'invoice_number', 'status']);
    }

    private function openInvoice(?int $invoiceId): ?Invoice
    {
        if (! $invoiceId) {
            return null;
        }

        return Invoice::query()
            ->whereKey($invoiceId)
            ->whereIn('status', self::OPEN_INVOICE)
            ->first(['id', 'invoice_number', 'status']);
    }

    /**
     * Cek keberadaan baris, termasuk yang sudah di-soft-delete.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     */
    private function exists(string $model, string $column, int $id): bool
    {
        $query = $model::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withTrashed();
        }

        return $query->where($column, $id)->exists();
    }

    /**
     * Kunci baris, cek aturan, lalu hapus, semuanya dalam satu transaksi,
     * supaya pembayaran/webhook yang masuk di antara "cek" dan "hapus" tidak
     * lolos begitu saja. Mengembalikan alasan penolakan, atau null kalau
     * berhasil dihapus.
     *
     * @param callable(Model): ?string $check   biasanya fn ($m) => $guard->forInvoice($m)
     * @param callable(Model): void|null $delete default: $model->delete()
     */
    public function deleteLocked(Model $model, callable $check, ?callable $delete = null): ?string
    {
        return DB::transaction(function () use ($model, $check, $delete) {
            $locked = $model->newQuery()->lockForUpdate()->findOrFail($model->getKey());

            if ($reason = $check($locked)) {
                return $reason;
            }

            $delete ? $delete($locked) : $locked->delete();

            return null;
        });
    }

    /**
     * Jejak audit penghapusan di activity log admin.
     */
    public function audit(string $type, string $title, ?string $detail = null, ?int $clientId = null): void
    {
        $who = auth('admin')->user()->name ?? 'admin';

        ActivityLog::record($type, $title, trim("Dihapus oleh {$who}. " . $detail), null, 'warning', $clientId);
    }
}
