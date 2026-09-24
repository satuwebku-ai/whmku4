<?php

declare(strict_types=1);

/**
 * One-shot SQLite ETL for the fresh-install baseline.
 *
 * The target must already contain the fresh schema and must be empty.
 * Nothing is changed unless --apply is supplied. On any error, the target
 * transaction is rolled back.
 *
 * Usage:
 *   php scripts/legacy-baseline-etl.php \
 *       --source=/backup/legacy.sqlite \
 *       --target=/tmp/baseline.sqlite
 *
 *   php scripts/legacy-baseline-etl.php \
 *       --source=/backup/legacy.sqlite \
 *       --target=/tmp/baseline.sqlite \
 *       --apply --json
 */

$options = getopt('', ['source:', 'target:', 'apply', 'json']);
$sourcePath = $options['source'] ?? null;
$targetPath = $options['target'] ?? null;
$json = isset($options['json']);

if (! is_string($sourcePath) || ! is_string($targetPath) || $sourcePath === '' || $targetPath === '') {
    fwrite(
        STDERR,
        "Usage: php scripts/legacy-baseline-etl.php --source=/path/legacy.sqlite --target=/path/baseline.sqlite [--apply] [--json]\n"
    );
    exit(2);
}

if (! is_file($sourcePath) || ! is_file($targetPath)) {
    fwrite(STDERR, "Source dan target harus berupa file SQLite yang sudah ada.\n");
    exit(2);
}

$connect = static function (string $path): PDO {
    return new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
};

try {
    $source = $connect($sourcePath);
    $target = $connect($targetPath);
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal membuka database: {$e->getMessage()}\n");
    exit(1);
}

$tableExists = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        "select count(*) from sqlite_master where type = 'table' and name = :table"
    );
    $stmt->execute(['table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
};

$quote = static fn (string $value): string => '"' . str_replace('"', '""', $value) . '"';

$tableColumns = static function (PDO $pdo, string $table): array {
    $stmt = $pdo->query('pragma table_info("' . str_replace('"', '""', $table) . '")');
    $columns = [];

    foreach ($stmt->fetchAll() as $column) {
        // SQLite generated columns are not writable.
        if ((int) ($column['hidden'] ?? 0) === 2 || (int) ($column['hidden'] ?? 0) === 3) {
            continue;
        }
        $columns[] = (string) $column['name'];
    }

    return $columns;
};

$targetTables = static function (PDO $pdo): array {
    $rows = $pdo->query(
        "select name from sqlite_master
         where type = 'table'
           and name not like 'sqlite_%'
         order by name"
    )->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_filter(
        array_map('strval', $rows),
        static fn (string $table): bool => ! in_array($table, [
            'migrations',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'sessions',
        ], true)
    ));
};

$aliases = [
    'cms_pages' => ['cms_pages', 'pages'],
    'product_groups' => ['product_groups', 'product_categories'],
    'credits' => ['credits', 'client_balance_logs'],
    'coupon_product_group' => ['coupon_product_group', 'coupon_product_category'],
];

$sourceTableFor = static function (string $targetTable) use ($source, $tableExists, $aliases): ?string {
    foreach ([$targetTable, ...($aliases[$targetTable] ?? [])] as $candidate) {
        if ($tableExists($source, $candidate)) {
            return $candidate;
        }
    }

    return null;
};

$countRows = static function (PDO $pdo, string $table): int {
    return (int) $pdo->query(
        'select count(*) from "' . str_replace('"', '""', $table) . '"'
    )->fetchColumn();
};

$rowsEquivalent = static function (
    PDO $left,
    string $leftTable,
    PDO $right,
    string $rightTable,
    array $columns
) use ($quote): bool {
    if ($columns === []) {
        return true;
    }

    $quoted = implode(', ', array_map($quote, $columns));
    $order = in_array('id', $columns, true) ? $quote('id') : 'rowid';
    $leftRows = $left->query(
        "select {$quoted} from {$quote($leftTable)} order by {$order}"
    )->fetchAll();
    $rightRows = $right->query(
        "select {$quoted} from {$quote($rightTable)} order by {$order}"
    )->fetchAll();

    return $leftRows === $rightRows;
};

$report = [
    'source' => realpath($sourcePath) ?: $sourcePath,
    'target' => realpath($targetPath) ?: $targetPath,
    'apply' => isset($options['apply']),
    'tables' => [],
    'warnings' => [],
];

if (! $tableExists($target, 'migrations')) {
    $report['warnings'][] = 'Target belum memiliki tabel migrations; jalankan migrate:fresh pada target kosong terlebih dahulu.';
}

$targetTablesList = $targetTables($target);
if ($targetTablesList === []) {
    $report['warnings'][] = 'Target tidak memiliki tabel aplikasi.';
}

$nonEmptyTargetTables = [];
$preseededTables = ['nav_menus', 'document_requirements', 'document_requirement_tld'];
foreach ($targetTablesList as $table) {
    if ($countRows($target, $table) > 0 && ! in_array($table, $preseededTables, true)) {
        $nonEmptyTargetTables[$table] = $countRows($target, $table);
    }
}

if ($nonEmptyTargetTables !== []) {
    $report['warnings'][] = 'Target tidak kosong; ETL dibatalkan untuk mencegah duplikasi.';
}

foreach ($targetTablesList as $targetTable) {
    $sourceTable = $sourceTableFor($targetTable);
    if ($sourceTable === null) {
        $report['tables'][$targetTable] = [
            'source_table' => null,
            'source_rows' => 0,
            'common_columns' => [],
            'action' => 'skip_missing_source',
        ];
        continue;
    }

    $sourceColumns = $tableColumns($source, $sourceTable);
    $targetColumns = $tableColumns($target, $targetTable);
    $commonColumns = array_values(array_intersect($targetColumns, $sourceColumns));
    $missingRequiredColumns = [];
    $preseededIdentical = false;

    if (
        in_array($targetTable, $preseededTables, true)
        && $countRows($target, $targetTable) > 0
        && $countRows($source, $sourceTable) > 0
    ) {
        $comparisonColumns = array_values(array_diff($commonColumns, ['created_at', 'updated_at']));
        $preseededIdentical = $rowsEquivalent(
            $target,
            $targetTable,
            $source,
            $sourceTable,
            $comparisonColumns
        );
        if (! $preseededIdentical) {
            $report['warnings'][] = "Target pre-seeded table {$targetTable} berbeda dari source.";
        }
    }

    foreach ($target->query('pragma table_info("' . str_replace('"', '""', $targetTable) . '")')->fetchAll() as $column) {
        $name = (string) $column['name'];
        if (
            (int) $column['notnull'] === 1
            && $column['dflt_value'] === null
            && ! in_array($name, $commonColumns, true)
        ) {
            $missingRequiredColumns[] = $name;
        }
    }

    $report['tables'][$targetTable] = [
        'source_table' => $sourceTable,
        'source_rows' => $countRows($source, $sourceTable),
        'common_columns' => $commonColumns,
        'missing_required_columns' => $missingRequiredColumns,
        'action' => $missingRequiredColumns !== []
            ? 'blocked_missing_required_columns'
            : ($preseededIdentical ? 'skip_preseeded_identical' : 'copy'),
    ];
}

$hasBlockingIssue = $report['warnings'] !== [];
foreach ($report['tables'] as $details) {
    if ($details['action'] === 'blocked_missing_required_columns') {
        $hasBlockingIssue = true;
    }
}

if ($json && ! isset($options['apply'])) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

if (! isset($options['apply'])) {
    if (! $json) {
        echo "Legacy baseline ETL plan (DRY-RUN)\n";
        echo "Target tidak akan diubah. Tambahkan --apply setelah plan direview.\n\n";
        foreach ($report['tables'] as $table => $details) {
            printf(
                "  %-32s <- %-28s %8d rows  %s\n",
                $table,
                $details['source_table'] ?? '(missing)',
                $details['source_rows'],
                $details['action']
            );
        }
    }
    exit($hasBlockingIssue ? 1 : 0);
}

if ($hasBlockingIssue) {
    if (! $json) {
        fwrite(STDERR, "ETL dibatalkan karena plan memiliki blocking issue.\n");
        foreach ($report['warnings'] as $warning) {
            fwrite(STDERR, " - {$warning}\n");
        }
    }
    echo $json ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL : '';
    exit(1);
}

try {
    // SQLite cannot toggle foreign_keys while a transaction is active.
    $target->exec('pragma foreign_keys = off');
    $target->beginTransaction();

    foreach ($report['tables'] as $targetTable => $details) {
        if ($details['action'] !== 'copy' || $details['source_rows'] === 0) {
            continue;
        }

        $columns = $details['common_columns'];
        $quotedColumns = implode(', ', array_map($quote, $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insert = $target->prepare(
            "insert into {$quote($targetTable)} ({$quotedColumns}) values ({$placeholders})"
        );
        $select = $source->query(
            "select {$quotedColumns} from {$quote($details['source_table'])}"
        );

        $copied = 0;
        foreach ($select as $row) {
            $insert->execute(array_map(
                static fn (string $column): mixed => $row[$column],
                $columns
            ));
            $copied++;
        }

        $report['tables'][$targetTable]['copied_rows'] = $copied;
    }

    $foreignKeyViolations = $target->query('pragma foreign_key_check')->fetchAll();
    if ($foreignKeyViolations !== []) {
        throw new RuntimeException(
            'foreign_key_check menemukan ' . count($foreignKeyViolations) . ' violation.'
        );
    }

    $target->exec('pragma foreign_keys = on');
    $target->commit();
} catch (Throwable $e) {
    if ($target->inTransaction()) {
        $target->rollBack();
    }

    $target->exec('pragma foreign_keys = on');
    fwrite(STDERR, "ETL gagal dan seluruh perubahan di-rollback: {$e->getMessage()}\n");
    exit(1);
}

echo $json
    ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    : "ETL selesai dan target sudah tervalidasi dengan foreign_key_check.\n";