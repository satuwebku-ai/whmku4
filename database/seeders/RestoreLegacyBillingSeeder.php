<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Pulihkan order, invoice, rincian invoice, dan pembayaran dari snapshot
 * SQL Satucloud tanggal 4 Oktober 2026.
 *
 * Jalankan manual; seeder ini sengaja tidak dipanggil dari DatabaseSeeder.
 * Aman dijalankan ulang: data yang sudah ada tidak ditimpa, soft-delete
 * dipulihkan, dan relasi lama dipetakan ke ID yang berlaku sekarang.
 */
class RestoreLegacyBillingSeeder extends Seeder
{
    /** @var array<int, int|null> */
    private array $clientIds = [];

    /** @var array<int, int> */
    private array $orderIds = [];

    /** @var array<int, int> */
    private array $invoiceIds = [];

    /** @var array<string, array<string, int>> */
    private array $counts = [
        'inserted' => [],
        'already_present' => [],
        'restored_from_trash' => [],
        'skipped' => [],
    ];

    /** @var array<string, array<int, string>> */
    private array $columnCache = [];

    private ?int $duitkuGatewayId = null;

    public function run(): void
    {
        foreach (['clients', 'domains', 'orders', 'invoices', 'payments'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Tabel wajib '{$table}' tidak ditemukan.");
            }
        }

        DB::transaction(function (): void {
            $this->clientIds = $this->resolveClientIds();
            $this->duitkuGatewayId = $this->resolveDuitkuGatewayId();

            $this->restoreOrders();
            $this->restoreInvoices();
            $this->restoreInvoiceItems();
            $this->restorePayments();
            $this->restoreDomainLinks();
        });

        $this->report();
    }

    /**
     * Domain pada snapshot menjadi petunjuk untuk mencari client yang sama
     * di database sekarang; ID lama hanya dipakai sebagai fallback.
     *
     * @return array<int, int|null>
     */
    private function resolveClientIds(): array
    {
        $legacyClients = [
            14 => ['appdataku.my.id', 'dataku22.ac.id'],
            17 => ['webku22.com'],
        ];
        $resolved = [];

        foreach ($legacyClients as $legacyId => $domainNames) {
            $owners = DB::table('domains')
                ->whereIn('domain_name', $domainNames)
                ->distinct()
                ->pluck('client_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($owners->count() === 1) {
                $resolved[$legacyId] = $owners->first();
                continue;
            }

            if ($owners->count() > 1) {
                $this->warn("Domain pada snapshot untuk client ID {$legacyId} menunjuk ke beberapa client; memakai ID lama jika masih ada.");
            }

            $resolved[$legacyId] = DB::table('clients')
                ->where('id', $legacyId)
                ->value('id');

            if ($resolved[$legacyId] !== null) {
                $resolved[$legacyId] = (int) $resolved[$legacyId];
            } else {
                $this->warn("Client snapshot ID {$legacyId} tidak ditemukan; data baru milik client ini akan dilewati.");
            }
        }

        return $resolved;
    }

    private function resolveDuitkuGatewayId(): ?int
    {
        if (! Schema::hasTable('payment_gateways')
            || ! Schema::hasColumn('payment_gateways', 'driver')
            || ! Schema::hasColumn('payment_gateways', 'id')) {
            return null;
        }

        $query = DB::table('payment_gateways')->where('driver', 'duitku');
        if (Schema::hasColumn('payment_gateways', 'name')) {
            $named = (clone $query)->where('name', 'DUITKU')->value('id');
            if ($named !== null) {
                return (int) $named;
            }
        }

        $id = $query->value('id');

        return $id === null ? null : (int) $id;
    }

    private function restoreOrders(): void
    {
        $rows = [
            [
                'id' => 5, 'order_number' => 'ORD-1001', 'client_id' => 14,
                'product_id' => null, 'hosting_account_id' => null,
                'product_name' => 'Registrasi Domain appdataku.my.id',
                'order_type' => 'domain', 'amount' => '40000.00',
                'legacy_status' => 'active',
                'created_at' => '2026-08-15 01:47:44', 'updated_at' => '2026-08-15 01:53:56',
            ],
            [
                'id' => 6, 'order_number' => 'ORD-1002', 'client_id' => 14,
                'product_id' => 1, 'hosting_account_id' => 5,
                'product_name' => 'Starter Host 1000',
                'order_type' => 'hosting', 'amount' => '10000.00',
                'legacy_status' => 'active',
                'created_at' => '2026-08-18 02:13:58', 'updated_at' => '2026-08-18 07:44:07',
            ],
            [
                'id' => 7, 'order_number' => 'ORD-1003', 'client_id' => 17,
                'product_id' => null, 'hosting_account_id' => null,
                'product_name' => 'Registrasi Domain webku22.com',
                'order_type' => 'domain', 'amount' => '179000.00',
                'legacy_status' => 'pending',
                'created_at' => '2026-08-21 09:13:47', 'updated_at' => '2026-08-21 09:13:47',
            ],
            [
                'id' => 9, 'order_number' => 'ORD-1004', 'client_id' => 14,
                'product_id' => null, 'hosting_account_id' => null,
                'product_name' => 'Registrasi Domain dataku22.ac.id',
                'order_type' => 'domain', 'amount' => '79000.00',
                'legacy_status' => 'pending',
                'created_at' => '2026-08-30 07:25:40', 'updated_at' => '2026-08-30 07:25:40',
            ],
        ];

        foreach ($rows as $row) {
            $legacyId = (int) $row['id'];
            $legacyStatus = $row['legacy_status'];
            $clientId = $this->clientIds[(int) $row['client_id']] ?? null;

            if ($clientId === null) {
                $this->count('skipped', 'orders');
                continue;
            }

            unset($row['legacy_status']);
            $row['client_id'] = $clientId;
            $row['status'] = $this->mapOrderStatus($legacyStatus);
            $row['completed_at'] = $legacyStatus === 'active' ? $row['updated_at'] : null;
            $row['product_id'] = $this->validForeignId('products', $row['product_id']);
            $row['hosting_account_id'] = $this->validForeignId('hosting_accounts', $row['hosting_account_id']);

            $this->restoreRecord('orders', $row, 'order_number', $legacyId, $this->orderIds);
        }
    }

    private function restoreInvoices(): void
    {
        $rows = [
            [
                'id' => 5, 'invoice_number' => 'INV-2026-0001', 'client_id' => 14, 'legacy_order_id' => 5,
                'amount' => '40000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '40000.00',
                'status' => 'paid', 'is_topup' => 0, 'issue_date' => '2026-08-15', 'due_date' => '2026-08-18',
                'paid_at' => '2026-08-15', 'payment_method' => 'DUITKU', 'notes' => null,
                'created_at' => '2026-08-15 01:47:36', 'updated_at' => '2026-08-15 01:53:50',
            ],
            [
                'id' => 6, 'invoice_number' => 'INV-2026-0002', 'client_id' => 14, 'legacy_order_id' => null,
                'amount' => '50000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '50000.00',
                'status' => 'unpaid', 'is_topup' => 1, 'issue_date' => '2026-08-18', 'due_date' => '2026-08-21',
                'paid_at' => null, 'payment_method' => null, 'notes' => null,
                'created_at' => '2026-08-17 20:49:17', 'updated_at' => '2026-08-17 20:49:17',
            ],
            [
                'id' => 7, 'invoice_number' => 'INV-2026-0003', 'client_id' => 14, 'legacy_order_id' => null,
                'amount' => '85000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '85000.00',
                'status' => 'unpaid', 'is_topup' => 0, 'issue_date' => '2026-08-18', 'due_date' => '2026-08-21',
                'paid_at' => null, 'payment_method' => null, 'notes' => null,
                'created_at' => '2026-08-18 02:01:47', 'updated_at' => '2026-08-18 02:01:47',
            ],
            [
                'id' => 8, 'invoice_number' => 'INV-2026-0004', 'client_id' => 14, 'legacy_order_id' => 6,
                'amount' => '10000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '10000.00',
                'status' => 'paid', 'is_topup' => 0, 'issue_date' => '2026-08-18', 'due_date' => '2026-08-21',
                'paid_at' => '2026-08-18', 'payment_method' => 'DUITKU', 'notes' => null,
                'created_at' => '2026-08-18 02:13:53', 'updated_at' => '2026-08-18 02:16:47',
            ],
            [
                'id' => 9, 'invoice_number' => 'INV-2026-0005', 'client_id' => 14, 'legacy_order_id' => null,
                'amount' => '10000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '10000.00',
                'status' => 'paid', 'is_topup' => 1, 'issue_date' => '2026-08-19', 'due_date' => '2026-08-22',
                'paid_at' => '2026-08-19', 'payment_method' => 'DUITKU', 'notes' => null,
                'created_at' => '2026-08-18 21:25:19', 'updated_at' => '2026-08-18 21:26:58',
            ],
            [
                'id' => 10, 'invoice_number' => 'INV-2026-0006', 'client_id' => 14, 'legacy_order_id' => null,
                'amount' => '2000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '2000.00',
                'status' => 'paid', 'is_topup' => 0, 'issue_date' => '2026-08-19', 'due_date' => '2026-08-22',
                'paid_at' => '2026-08-19', 'payment_method' => 'Saldo', 'notes' => null,
                'created_at' => '2026-08-18 21:28:49', 'updated_at' => '2026-08-18 21:29:03',
            ],
            [
                'id' => 12, 'invoice_number' => 'INV-2026-0007', 'client_id' => 14, 'legacy_order_id' => null,
                'amount' => '12000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '12000.00',
                'status' => 'paid', 'is_topup' => 0, 'issue_date' => '2026-08-21', 'due_date' => '2026-09-18',
                'paid_at' => '2026-08-28', 'payment_method' => 'DUITKU', 'notes' => null,
                'created_at' => '2026-08-21 02:23:08', 'updated_at' => '2026-08-27 23:24:03',
            ],
            [
                'id' => 13, 'invoice_number' => 'INV-2026-0008', 'client_id' => 14, 'legacy_order_id' => null,
                'amount' => '40000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '40000.00',
                'status' => 'unpaid', 'is_topup' => 0, 'issue_date' => '2026-08-21', 'due_date' => '2027-08-15',
                'paid_at' => null, 'payment_method' => null, 'notes' => null,
                'created_at' => '2026-08-21 02:35:28', 'updated_at' => '2026-08-21 02:35:28',
            ],
            [
                'id' => 14, 'invoice_number' => 'INV-2026-0009', 'client_id' => 17, 'legacy_order_id' => 7,
                'amount' => '179000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '179000.00',
                'status' => 'unpaid', 'is_topup' => 0, 'issue_date' => '2026-08-21', 'due_date' => '2026-08-24',
                'paid_at' => null, 'payment_method' => null, 'notes' => null,
                'created_at' => '2026-08-21 09:13:39', 'updated_at' => '2026-08-21 09:13:47',
            ],
            [
                'id' => 18, 'invoice_number' => 'INV-2026-0010', 'client_id' => 14, 'legacy_order_id' => 9,
                'amount' => '79000.00', 'tax' => '0.00', 'discount' => '0.00', 'total' => '79000.00',
                'status' => 'unpaid', 'is_topup' => 0, 'issue_date' => '2026-08-30', 'due_date' => '2026-09-02',
                'paid_at' => null, 'payment_method' => null, 'notes' => null,
                'created_at' => '2026-08-30 07:25:40', 'updated_at' => '2026-08-30 07:25:40',
            ],
        ];

        foreach ($rows as $row) {
            $legacyId = (int) $row['id'];
            $legacyClientId = (int) $row['client_id'];
            $legacyOrderId = $row['legacy_order_id'];
            unset($row['legacy_order_id']);

            $clientId = $this->clientIds[$legacyClientId] ?? null;
            if ($clientId === null) {
                $this->count('skipped', 'invoices');
                continue;
            }

            $row['client_id'] = $clientId;
            $row['order_id'] = $legacyOrderId === null
                ? null
                : ($this->orderIds[(int) $legacyOrderId] ?? null);
            $this->restoreRecord('invoices', $row, 'invoice_number', $legacyId, $this->invoiceIds);
        }
    }

    private function restoreInvoiceItems(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            $this->warn('Tabel invoice_items belum ada; relasi order-invoice tetap dipulihkan melalui invoices.order_id.');
            return;
        }

        $rows = [
            [5, 5, 5, 'Registrasi Domain appdataku.my.id (1 tahun)', '40000.00', '2026-08-15 01:47:44'],
            [6, 6, null, 'Isi Ulang Saldo', '50000.00', '2026-08-17 20:49:22'],
            [7, 7, null, 'ID Protection — appdataku.my.id (1 tahun)', '85000.00', '2026-08-18 02:01:53'],
            [8, 8, 6, 'Starter Host 1000 (Bulanan)', '10000.00', '2026-08-18 02:13:58'],
            [9, 9, null, 'Isi Ulang Saldo', '10000.00', '2026-08-18 21:25:23'],
            [10, 10, null, 'Upgrade appdataku.my.id: Starter Host 1000 → Starter Host 5000 (prorata sisa siklus)', '2000.00', '2026-08-18 21:28:53'],
            [11, 12, null, 'Perpanjangan Hosting — appdataku.my.id (satuclo1_Starter Host 5000, bulanan)', '12000.00', '2026-08-21 02:23:18'],
            [12, 13, null, 'Perpanjangan Domain — appdataku.my.id (1 tahun)', '40000.00', '2026-08-21 02:35:35'],
            [13, 14, 7, 'Registrasi Domain webku22.com (1 tahun)', '179000.00', '2026-08-21 09:13:47'],
            [15, 18, 9, 'Registrasi Domain dataku22.ac.id (1 tahun)', '79000.00', '2026-08-30 07:25:47'],
        ];

        foreach ($rows as [$legacyId, $legacyInvoiceId, $legacyOrderId, $description, $amount, $createdAt]) {
            $invoiceId = $this->invoiceIds[(int) $legacyInvoiceId] ?? null;
            $orderId = $legacyOrderId === null ? null : ($this->orderIds[(int) $legacyOrderId] ?? null);
            if ($invoiceId === null || ($legacyOrderId !== null && $orderId === null)) {
                $this->count('skipped', 'invoice_items');
                continue;
            }

            $row = [
                'invoice_id' => $invoiceId,
                'order_id' => $orderId,
                'description' => $description,
                'amount' => $amount,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            $query = DB::table('invoice_items')
                ->where('invoice_id', $invoiceId)
                ->where('description', $description)
                ->where('amount', $amount);
            $legacyOrderId === null
                ? $query->whereNull('order_id')
                : $query->where('order_id', $orderId);

            if ($query->exists()) {
                $this->count('already_present', 'invoice_items');
                continue;
            }

            $payload = $this->existingColumns('invoice_items', $row);
            if (Schema::hasColumn('invoice_items', 'id')
                && ! DB::table('invoice_items')->where('id', $legacyId)->exists()) {
                $payload['id'] = $legacyId;
                DB::table('invoice_items')->insert($payload);
            } else {
                DB::table('invoice_items')->insert($payload);
            }
            $this->count('inserted', 'invoice_items');
        }
    }

    private function restorePayments(): void
    {
        $rows = [
            [
                'id' => 7, 'reference' => 'PAY-2026-0001', 'invoice_id' => 5, 'client_id' => 14,
                'amount' => '40000.00', 'fee' => '0.00', 'total' => '40000.00', 'status' => 'failed',
                'external_id' => null, 'payment_method' => 'SP', 'paid_at' => null,
                'created_at' => '2026-08-15 01:52:58', 'updated_at' => '2026-08-15 01:52:59',
            ],
            [
                'id' => 8, 'reference' => 'PAY-2026-0002', 'invoice_id' => 5, 'client_id' => 14,
                'amount' => '40000.00', 'fee' => '0.00', 'total' => '40000.00', 'status' => 'paid',
                'external_id' => 'D1668526P33XVRG7ZV7YRFL', 'payment_method' => 'SP', 'paid_at' => '2026-08-15 01:53:50',
                'created_at' => '2026-08-15 01:53:05', 'updated_at' => '2026-08-15 01:53:50',
            ],
            [
                'id' => 9, 'reference' => 'PAY-2026-0003', 'invoice_id' => 8, 'client_id' => 14,
                'amount' => '10000.00', 'fee' => '0.00', 'total' => '10000.00', 'status' => 'paid',
                'external_id' => 'D16685266KRMBKTWT7QJ367', 'payment_method' => 'SP', 'paid_at' => '2026-08-18 02:16:47',
                'created_at' => '2026-08-18 02:14:11', 'updated_at' => '2026-08-18 02:16:47',
            ],
            [
                'id' => 10, 'reference' => 'PAY-2026-0004', 'invoice_id' => 9, 'client_id' => 14,
                'amount' => '10000.00', 'fee' => '0.00', 'total' => '10000.00', 'status' => 'paid',
                'external_id' => 'D1668526UT5NREQPJDN48HS', 'payment_method' => 'SP', 'paid_at' => '2026-08-18 21:26:58',
                'created_at' => '2026-08-18 21:25:37', 'updated_at' => '2026-08-18 21:26:58',
            ],
            [
                'id' => 11, 'reference' => 'PAY-2026-0005', 'invoice_id' => 10, 'client_id' => 14,
                'amount' => '2000.00', 'fee' => '0.00', 'total' => '2000.00', 'status' => 'paid',
                'external_id' => null, 'payment_method' => 'Saldo', 'paid_at' => '2026-08-18 21:29:03',
                'created_at' => '2026-08-18 21:29:03', 'updated_at' => '2026-08-18 21:29:03',
            ],
            [
                'id' => 12, 'reference' => 'PAY-2026-0006', 'invoice_id' => 14, 'client_id' => 17,
                'amount' => '179000.00', 'fee' => '0.00', 'total' => '179000.00', 'status' => 'initiated',
                'external_id' => 'D1668526GDVYBJ4F5EUL6FN', 'payment_method' => 'SP', 'paid_at' => null,
                'created_at' => '2026-08-21 09:14:52', 'updated_at' => '2026-08-21 09:14:53',
            ],
            [
                'id' => 14, 'reference' => 'PAY-2026-0007', 'invoice_id' => 12, 'client_id' => 14,
                'amount' => '12000.00', 'fee' => '0.00', 'total' => '12000.00', 'status' => 'failed',
                'external_id' => null, 'payment_method' => 'SP', 'paid_at' => null,
                'created_at' => '2026-08-27 23:20:10', 'updated_at' => '2026-08-27 23:20:11',
            ],
            [
                'id' => 15, 'reference' => 'PAY-2026-0008', 'invoice_id' => 12, 'client_id' => 14,
                'amount' => '12000.00', 'fee' => '0.00', 'total' => '12000.00', 'status' => 'paid',
                'external_id' => 'D16685267L78P3TPO5O2BI2', 'payment_method' => 'SP', 'paid_at' => '2026-08-27 23:24:03',
                'created_at' => '2026-08-27 23:20:24', 'updated_at' => '2026-08-27 23:24:03',
            ],
        ];

        $paymentIds = [];
        foreach ($rows as $row) {
            $legacyId = (int) $row['id'];
            $legacyInvoiceId = (int) $row['invoice_id'];
            $legacyClientId = (int) $row['client_id'];
            $invoiceId = $this->invoiceIds[$legacyInvoiceId] ?? null;
            $clientId = $this->clientIds[$legacyClientId] ?? null;

            if ($invoiceId === null || $clientId === null) {
                $this->count('skipped', 'payments');
                continue;
            }

            $row['invoice_id'] = $invoiceId;
            $row['client_id'] = $clientId;
            $row['payment_gateway_id'] = $legacyId === 11 ? null : $this->duitkuGatewayId;
            $row['currency'] = 'IDR';
            $row['expires_at'] = null;
            $row['admin_note'] = null;

            // Gateway metadata, payment URLs, QR payloads, and signatures
            // are intentionally not copied from the old dump.
            if ($row['external_id'] !== null
                && $row['payment_gateway_id'] !== null
                && Schema::hasColumn('payments', 'payment_gateway_id')
                && Schema::hasColumn('payments', 'external_id')
                && DB::table('payments')
                    ->where('payment_gateway_id', $row['payment_gateway_id'])
                    ->where('external_id', $row['external_id'])
                    ->where('reference', '!=', $row['reference'])
                    ->exists()) {
                $row['external_id'] = null;
            }

            $this->restoreRecord('payments', $row, 'reference', $legacyId, $paymentIds);
        }
    }

    /**
     * Isi relasi domain dari snapshot hanya bila kolom kosong atau masih
     * menunjuk ke ID lama yang sekarang dipetakan ulang.
     */
    private function restoreDomainLinks(): void
    {
        $links = [
            'appdataku.my.id' => [
                'order_id' => [5, 'order'],
                'renewal_invoice_id' => [13, 'invoice'],
                'privacy_invoice_id' => [7, 'invoice'],
            ],
            'webku22.com' => [
                'order_id' => [7, 'order'],
            ],
            'dataku22.ac.id' => [
                'order_id' => [9, 'order'],
            ],
        ];

        foreach ($links as $domainName => $fields) {
            $domain = DB::table('domains')->where('domain_name', $domainName)->first();
            if (! $domain) {
                continue;
            }

            foreach ($fields as $column => [$legacyId, $type]) {
                if (! Schema::hasColumn('domains', $column)) {
                    continue;
                }

                $mappedId = $type === 'order'
                    ? ($this->orderIds[$legacyId] ?? null)
                    : ($this->invoiceIds[$legacyId] ?? null);
                if ($mappedId === null) {
                    continue;
                }

                $currentId = $domain->{$column};
                if ($currentId === null || (int) $currentId === $legacyId) {
                    if ((int) ($currentId ?? 0) !== $mappedId) {
                        DB::table('domains')
                            ->where('domain_name', $domainName)
                            ->update([$column => $mappedId]);
                    }
                } elseif ((int) $currentId !== $mappedId) {
                    $this->warn("Relasi domains.{$column} untuk {$domainName} berbeda dari snapshot; tidak diubah.");
                }
            }
        }
    }

    /**
     * Simpan tanpa Eloquent agar pemulihan tidak mengirim notifikasi,
     * menjalankan provisioning, atau mencatat ulang transaksi pembayaran.
     *
     * @param array<int, int> $idMap
     */
    private function restoreRecord(string $table, array $row, string $key, int $legacyId, array &$idMap): ?int
    {
        $existing = DB::table($table)->where($key, $row[$key])->first();

        if ($existing) {
            $id = (int) $existing->id;
            $changes = [];

            if (in_array('deleted_at', $this->columns($table), true) && $existing->deleted_at !== null) {
                $changes['deleted_at'] = null;
                $this->count('restored_from_trash', $table);
            } else {
                $this->count('already_present', $table);
            }

            if ($table === 'orders' && ! $this->usesLegacyOrderEnum()) {
                $currentStatus = (string) $existing->status;
                $normalizedStatus = match ($currentStatus) {
                    'active' => 'completed',
                    'pending' => 'pending_payment',
                    default => $currentStatus,
                };
                if ($normalizedStatus !== $currentStatus) {
                    $changes['status'] = $normalizedStatus;
                }
            }

            if ($changes !== []) {
                DB::table($table)->where('id', $id)->update($changes);
            }

            $idMap[$legacyId] = $id;
            return $id;
        }

        if (isset($row['client_id']) && ! DB::table('clients')->where('id', $row['client_id'])->exists()) {
            $this->count('skipped', $table);
            $this->warn("{$table} {$row[$key]} dilewati: client yang dirujuk tidak ada.");
            return null;
        }

        $row = $this->existingColumns($table, $row);

        if (in_array('id', $this->columns($table), true)
            && ! DB::table($table)->where('id', $legacyId)->exists()) {
            $row['id'] = $legacyId;
            DB::table($table)->insert($row);
            $id = $legacyId;
        } else {
            unset($row['id']);
            $id = (int) DB::table($table)->insertGetId($row);
        }

        $idMap[$legacyId] = $id;
        $this->count('inserted', $table);

        return $id;
    }

    /**
     * Status legacy active/pending tidak cocok dengan enum OrderStatus baru.
     * Pertahankan nilai lama hanya jika kolom database masih enum lama.
     */
    private function mapOrderStatus(string $legacyStatus): string
    {
        return match ($legacyStatus) {
            'active' => $this->usesLegacyOrderEnum() ? 'active' : 'completed',
            'pending' => $this->usesLegacyOrderEnum() ? 'pending' : 'pending_payment',
            default => $legacyStatus,
        };
    }

    private function validForeignId(string $table, ?int $id): ?int
    {
        if ($id === null || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return null;
        }

        return DB::table($table)->where('id', $id)->exists() ? $id : null;
    }

    private function usesLegacyOrderEnum(): bool
    {
        if (! Schema::hasColumn('orders', 'status')) {
            return false;
        }

        try {
            return strtolower(Schema::getColumnType('orders', 'status')) === 'enum';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function existingColumns(string $table, array $row): array
    {
        return array_intersect_key($row, array_flip($this->columns($table)));
    }

    /**
     * @return array<int, string>
     */
    private function columns(string $table): array
    {
        return $this->columnCache[$table] ??= Schema::getColumnListing($table);
    }

    private function count(string $kind, string $table): void
    {
        $this->counts[$kind][$table] = ($this->counts[$kind][$table] ?? 0) + 1;
    }

    private function warn(string $message): void
    {
        if ($this->command) {
            $this->command->warn($message);
        }
    }

    private function report(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('Pemulihan data billing dari snapshot SQL selesai.');
        foreach ($this->counts as $kind => $tables) {
            foreach ($tables as $table => $count) {
                $this->command->line("  {$kind}: {$table} = {$count}");
            }
        }
    }

}