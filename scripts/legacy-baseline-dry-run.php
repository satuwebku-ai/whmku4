<?php

declare(strict_types=1);

/**
 * Read-only report for the fresh-install baseline cutover.
 *
 * Usage:
 *   php scripts/legacy-baseline-dry-run.php --source=/path/to/legacy.sqlite
 *   php scripts/legacy-baseline-dry-run.php --source=/path/to/legacy.sqlite --json
 *
 * This script deliberately has no write path. It only reports source counts,
 * legacy table aliases, lifecycle values, and duplicate invariants that must
 * be resolved by a separately reviewed ETL before production cutover.
 */

$options = getopt('', ['source:', 'json']);
$source = $options['source'] ?? null;

if (! is_string($source) || $source === '') {
    fwrite(STDERR, "Usage: php scripts/legacy-baseline-dry-run.php --source=/path/to/legacy.sqlite [--json]\n");
    exit(2);
}

if (! is_file($source)) {
    fwrite(STDERR, "Source SQLite tidak ditemukan: {$source}\n");
    exit(2);
}

try {
    $pdo = new PDO('sqlite:' . $source, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal membuka SQLite source: {$e->getMessage()}\n");
    exit(1);
}

$tableExists = static function (string $table) use ($pdo): bool {
    $stmt = $pdo->prepare(
        "select count(*) from sqlite_master where type = 'table' and name = :table"
    );
    $stmt->execute(['table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
};

$count = static function (string $table) use ($pdo, $tableExists): ?int {
    if (! $tableExists($table)) {
        return null;
    }

    return (int) $pdo->query('select count(*) from "' . str_replace('"', '""', $table) . '"')->fetchColumn();
};

$firstExisting = static function (array $tables) use ($tableExists): ?string {
    foreach ($tables as $table) {
        if ($tableExists($table)) {
            return $table;
        }
    }

    return null;
};

$report = [
    'source' => realpath($source) ?: $source,
    'read_only' => true,
    'table_aliases' => [],
    'row_counts' => [],
    'order_statuses' => [],
    'payment_external_duplicates' => [],
    'coupon_invoice_duplicates' => [],
    'legacy_cleanup_candidates' => [],
    'notes' => [
        'Tidak ada INSERT, UPDATE, DELETE, DDL, atau transaksi write yang dijalankan.',
        'Alias lama harus dipetakan ke nama final oleh ETL yang direview terpisah.',
    ],
];

$aliases = [
    'cms_pages' => ['cms_pages', 'pages'],
    'product_groups' => ['product_groups', 'product_categories'],
    'credits' => ['credits', 'client_balance_logs'],
    'coupon_product_group' => ['coupon_product_group', 'coupon_product_category'],
];

foreach ($aliases as $final => $candidates) {
    $sourceTable = $firstExisting($candidates);
    $report['table_aliases'][$final] = [
        'source_table' => $sourceTable,
        'source_rows' => $sourceTable === null ? 0 : $count($sourceTable),
    ];
}

foreach ([
    'clients',
    'orders',
    'invoices',
    'payments',
    'hosting_accounts',
    'domains',
    'credits',
    'cms_pages',
    'product_groups',
    'coupon_usages',
    'notification_deliveries',
    'affiliate_commissions',
    'affiliate_payouts',
] as $table) {
    $sourceTable = $aliases[$table][0] ?? $table;
    $actual = $firstExisting([$sourceTable, ...($aliases[$table] ?? [])]);
    $report['row_counts'][$table] = $actual === null ? null : $count($actual);
}

if ($tableExists('orders')) {
    $rows = $pdo->query('select status, count(*) as total from orders group by status order by status')->fetchAll();
    foreach ($rows as $row) {
        $report['order_statuses'][(string) $row['status']] = (int) $row['total'];
    }
}

if ($tableExists('payments')) {
    $report['payment_external_duplicates'] = $pdo->query(
        'select payment_gateway_id, external_id, count(*) as total
         from payments
         where external_id is not null
         group by payment_gateway_id, external_id
         having count(*) > 1
         order by total desc'
    )->fetchAll();
}

if ($tableExists('coupon_usages')) {
    $report['coupon_invoice_duplicates'] = $pdo->query(
        'select invoice_id, count(*) as total
         from coupon_usages
         where invoice_id is not null
         group by invoice_id
         having count(*) > 1
         order by total desc'
    )->fetchAll();
}

if ($tableExists('hosting_accounts')) {
    $report['legacy_cleanup_candidates']['terminated_hosting_with_renewal_invoice'] = (int) $pdo->query(
        "select count(*) from hosting_accounts
         where status = 'terminated' and renewal_invoice_id is not null"
    )->fetchColumn();
}

if ($tableExists('domains') && $tableExists('invoices')) {
    $report['legacy_cleanup_candidates']['early_domain_renewal_invoices'] = (int) $pdo->query(
        "select count(*)
         from domains d
         join invoices i on i.id = d.renewal_invoice_id
         where d.status = 'active'
           and d.expiry_date is not null
           and i.status in ('unpaid', 'overdue')
           and i.issue_date < date(d.expiry_date, '-30 day')"
    )->fetchColumn();
}

if (isset($options['json'])) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

echo "Legacy baseline dry-run (READ ONLY)\n";
echo "Source: {$report['source']}\n\n";
echo "Table aliases:\n";
foreach ($report['table_aliases'] as $final => $details) {
    printf(
        "  %-24s <- %-28s (%d rows)\n",
        $final,
        $details['source_table'] ?? '(missing)',
        $details['source_rows']
    );
}

echo "\nRow counts:\n";
foreach ($report['row_counts'] as $table => $rows) {
    printf("  %-28s %s\n", $table, $rows === null ? '(missing)' : (string) $rows);
}

echo "\nOrder statuses:\n";
foreach ($report['order_statuses'] as $status => $total) {
    printf("  %-28s %d\n", $status, $total);
}

printf("\nDuplicate payment external IDs: %d\n", count($report['payment_external_duplicates']));
printf("Duplicate coupon invoice reservations: %d\n", count($report['coupon_invoice_duplicates']));
echo "Cleanup candidates:\n";
foreach ($report['legacy_cleanup_candidates'] as $name => $total) {
    printf("  %-52s %d\n", $name, $total);
}

echo "\nTidak ada perubahan yang dilakukan.\n";