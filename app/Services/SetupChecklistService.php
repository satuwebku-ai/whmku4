<?php

namespace App\Services;

use App\Models\CronJob;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\Registrar;
use App\Models\Server;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Daftar periksa "apa saja yang belum jalan di aplikasi" untuk modal
 * yang muncul saat admin login (lihat partial setup-checklist-modal).
 *
 * Tiap item dicek langsung dari kondisi nyata (bukan centang manual),
 * jadi otomatis ikut tercentang begitu masalahnya diperbaiki. Item yang
 * memang tidak dipakai di situs ini (mis. WhatsApp, registrar) bisa
 * "dilewati" oleh admin — item yang dilewati dianggap selesai supaya
 * modal tidak muncul terus-menerus.
 */
class SetupChecklistService
{
    private const SKIP_KEY = 'setup_checklist_skipped';

    /**
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   total: int, done: int, pending: int, percent: int
     * }
     */
    public function summary(): array
    {
        $skipped = $this->skippedKeys();
        $items = [];

        foreach ($this->definitions() as $key => $def) {
            [$ok, $detail] = $this->safely($def['check']);

            $isSkipped = $def['skippable'] && in_array($key, $skipped, true) && ! $ok;

            $items[] = [
                'key' => $key,
                'title' => $def['title'],
                'description' => $def['description'],
                'module' => $def['module'],
                'url' => $def['url'] ? $def['url']() : null,
                'skippable' => $def['skippable'],
                'ok' => $ok,
                'skipped' => $isSkipped,
                'complete' => $ok || $isSkipped,
                'detail' => $ok ? null : $detail,
            ];
        }

        $total = count($items);
        $done = count(array_filter($items, fn ($i) => $i['complete']));

        return [
            'items' => $items,
            'total' => $total,
            'done' => $done,
            'pending' => $total - $done,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 100,
        ];
    }

    public function skippedKeys(): array
    {
        $raw = Setting::get(self::SKIP_KEY);
        $list = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($list) ? array_values($list) : [];
    }

    /**
     * Tandai item sebagai "tidak dipakai". False kalau kunci tidak
     * dikenal atau item itu memang wajib (tidak boleh dilewati).
     */
    public function skip(string $key): bool
    {
        $def = $this->definitions()[$key] ?? null;

        if (! $def || ! $def['skippable']) {
            return false;
        }

        $list = array_values(array_unique([...$this->skippedKeys(), $key]));
        Setting::put(self::SKIP_KEY, json_encode($list), 'general');

        return true;
    }

    public function restore(string $key): bool
    {
        if (! isset($this->definitions()[$key])) {
            return false;
        }

        $list = array_values(array_diff($this->skippedKeys(), [$key]));
        Setting::put(self::SKIP_KEY, json_encode($list), 'general');

        return true;
    }

    // ── Definisi item ────────────────────────────────────────────────

    /**
     * Setiap check() mengembalikan [bool lolos, ?string keterangan masalah].
     */
    private function definitions(): array
    {
        return [
            'cron' => [
                'title' => 'Cron Jobs berjalan',
                'description' => 'Pengingat tagihan, invoice perpanjangan, auto-suspend, dan tugas otomatis lain hanya jalan kalau cron server aktif.',
                'module' => 'system',
                'skippable' => false,
                'url' => fn () => route('admin.cron.index'),
                'check' => fn () => $this->checkCron(),
            ],
            'migrations' => [
                'title' => 'Database sudah up-to-date',
                'description' => 'Semua migrasi sudah dijalankan, supaya tidak ada halaman yang error karena tabel/kolom belum ada.',
                'module' => 'system',
                'skippable' => false,
                'url' => null,
                'check' => fn () => $this->checkMigrations(),
            ],
            'schema' => [
                'title' => 'Tabel & kolom database lengkap',
                'description' => 'Kolom yang dipakai fitur terbaru (halaman Promo, TLD di beranda) sudah ada, supaya tidak muncul error 500.',
                'module' => 'system',
                'skippable' => false,
                'url' => null,
                'check' => fn () => $this->checkSchema(),
            ],
            'mail' => [
                'title' => 'Email (SMTP) aktif',
                'description' => 'Dibutuhkan untuk OTP admin, reset password, invoice, dan pengingat tagihan.',
                'module' => 'system',
                'skippable' => false,
                'url' => null,
                'check' => fn () => $this->checkMail(),
            ],
            'storage_link' => [
                'title' => 'Symlink storage sudah dibuat',
                'description' => 'Tanpa ini lampiran tiket dan file upload tidak bisa diakses dari browser.',
                'module' => 'system',
                'skippable' => false,
                'url' => null,
                'check' => fn () => $this->checkStorageLink(),
            ],
            'payment_gateway' => [
                'title' => 'Payment gateway aktif',
                'description' => 'Minimal satu metode pembayaran aktif supaya klien bisa membayar invoice.',
                'module' => 'billing',
                'skippable' => false,
                'url' => fn () => route('admin.gateways'),
                'check' => fn () => $this->hasActive(PaymentGateway::class, 'payment_gateways', 'Belum ada payment gateway yang aktif.'),
            ],
            'product' => [
                'title' => 'Produk sudah tersedia',
                'description' => 'Minimal satu produk aktif supaya katalog toko tidak kosong.',
                'module' => 'sales',
                'skippable' => false,
                'url' => fn () => route('admin.products.index'),
                'check' => fn () => $this->hasActive(Product::class, 'products', 'Belum ada produk yang aktif.'),
            ],
            'server' => [
                'title' => 'Server hosting terhubung',
                'description' => 'Server cPanel/WHM (atau panel lain) untuk membuat akun hosting otomatis setelah pembayaran.',
                'module' => 'infrastructure',
                'skippable' => true,
                'url' => fn () => route('admin.servers.index'),
                'check' => fn () => $this->hasActive(Server::class, 'servers', 'Belum ada server yang aktif.'),
            ],
            'registrar' => [
                'title' => 'Registrar domain terhubung',
                'description' => 'Dibutuhkan untuk cek ketersediaan, registrasi, dan transfer domain.',
                'module' => 'infrastructure',
                'skippable' => true,
                'url' => fn () => route('admin.registrars.index'),
                'check' => fn () => $this->hasActive(Registrar::class, 'registrars', 'Belum ada registrar yang aktif.'),
            ],
            'backup' => [
                'title' => 'Backup otomatis berjalan',
                'description' => 'Cadangan database & file upload dibuat rutin sehingga data aman kalau terjadi masalah.',
                'module' => 'infrastructure',
                'skippable' => true,
                'url' => fn () => route('admin.backups.index'),
                'check' => fn () => $this->checkBackup(),
            ],
            'whatsapp' => [
                'title' => 'Notifikasi WhatsApp aktif',
                'description' => 'Gateway WhatsApp (Fonnte/Wablas) untuk pengingat tagihan dan notifikasi ke klien.',
                'module' => 'system',
                'skippable' => true,
                'url' => fn () => route('admin.settings.notifications'),
                'check' => fn () => $this->checkWhatsApp(),
            ],
            'debug_off' => [
                'title' => 'Mode debug dimatikan',
                'description' => 'APP_DEBUG=true di server produksi bisa membocorkan detail error dan konfigurasi.',
                'module' => 'system',
                'skippable' => true,
                'url' => null,
                'check' => fn () => config('app.debug')
                    ? [false, 'APP_DEBUG masih true — ubah ke false di file .env (abaikan kalau ini lingkungan lokal).']
                    : [true, null],
            ],
        ];
    }

    // ── Pengecekan ───────────────────────────────────────────────────

    private function checkCron(): array
    {
        if (! Schema::hasTable('cron_jobs')) {
            return [false, 'Tabel cron_jobs belum ada — jalankan php artisan migrate.'];
        }

        CronJob::syncBuiltIn();

        $jobs = CronJob::where('is_enabled', true)->get();

        if ($jobs->isEmpty()) {
            return [false, 'Semua tugas cron dinonaktifkan.'];
        }

        if ($jobs->whereNotNull('last_run_at')->isEmpty()) {
            return [false, 'Belum pernah berjalan — baris cron di server belum dipasang.'];
        }

        $overdue = $jobs->filter(fn (CronJob $job) => $job->isOverdue())->count();

        if ($overdue > 0) {
            return [false, "{$overdue} tugas terlambat dijalankan — cron server kemungkinan berhenti."];
        }

        return [true, null];
    }

    private function checkMigrations(): array
    {
        $migrator = app('migrator');
        $repository = $migrator->getRepository();

        if (! $repository->repositoryExists()) {
            return [false, 'Tabel migrations belum ada — jalankan php artisan migrate.'];
        }

        $paths = array_merge([database_path('migrations')], $migrator->paths());
        $files = array_keys($migrator->getMigrationFiles($paths));
        $pending = count(array_diff($files, $repository->getRan()));

        return $pending > 0
            ? [false, "{$pending} migrasi belum dijalankan — jalankan php artisan migrate."]
            : [true, null];
    }

    /**
     * Kolom yang wajib ada. Migrasi yang sudah tercatat "selesai" tidak
     * dijalankan ulang walau isinya berubah, jadi kolom baru bisa hilang
     * padahal checkMigrations() menyatakan beres.
     */
    private const REQUIRED_COLUMNS = [
        'tlds'    => ['show_in_search', 'show_on_home'],
        'coupons' => ['title', 'description', 'tld_ids', 'is_public'],
    ];

    private function checkSchema(): array
    {
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $missing[] = "tabel {$table}";
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        return $missing === []
            ? [true, null]
            : [false, 'Belum ada: ' . implode(', ', $missing) . '. Tambahkan lewat ALTER TABLE (lihat panduan), lalu jalankan optimize:clear.'];
    }

    private function checkMail(): array
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return [false, 'MAIL_MAILER masih "' . config('mail.default') . '" — email tidak benar-benar terkirim (OTP & reset password tidak akan sampai).'];
        }

        $from = (string) config('mail.from.address');

        if (blank($from) || $from === 'hello@example.com') {
            return [false, 'MAIL_FROM_ADDRESS belum diisi dengan alamat pengirim yang sebenarnya.'];
        }

        return [true, null];
    }

    private function checkStorageLink(): array
    {
        $target = realpath(storage_path('app/public'));

        if ($target === false) {
            return [false, 'Folder storage/app/public belum ada — buat dengan: mkdir -p storage/app/public'];
        }

        // Di shared hosting, document root sering bukan public/ milik Laravel
        // (mis. public_html), jadi symlink bisa ada di salah satu lokasi ini.
        $candidates = array_filter(array_unique([
            public_path('storage'),
            ! empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/storage' : null,
            dirname(base_path()) . '/public_html/storage',
            base_path('public_html/storage'),
        ]));

        foreach ($candidates as $path) {
            // Symlink yang menunjuk ke storage/app/public
            if (is_link($path) && realpath($path) === $target) {
                return [true, null];
            }

            // Alternatif tanpa symlink: folder asli (disk public diarahkan ke sini)
            if (! is_link($path) && is_dir($path)) {
                return [true, null];
            }
        }

        return [false, 'Symlink belum ditemukan. Buat dengan: php artisan storage:link, atau lewat shell: ln -s ' . $target . ' <folder-web>/storage'];
    }

    private function checkBackup(): array
    {
        if (Setting::get('backup_enabled', '1') === '0') {
            return [false, 'Backup otomatis dimatikan.'];
        }

        $files = glob(storage_path('app/backups/lumora-backup_*.zip')) ?: [];

        if (empty($files)) {
            return [false, 'Belum ada satu pun file backup.'];
        }

        $newest = max(array_map('filemtime', $files));
        $days = (int) floor((time() - $newest) / 86400);

        return $days > 3
            ? [false, "Backup terakhir {$days} hari yang lalu."]
            : [true, null];
    }

    private function checkWhatsApp(): array
    {
        if (Setting::get('wa_provider', 'none') === 'none') {
            return [false, 'Provider WhatsApp belum dipilih.'];
        }

        if (blank(Setting::get('wa_token'))) {
            return [false, 'Token gateway WhatsApp belum diisi.'];
        }

        return [true, null];
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     */
    private function hasActive(string $model, string $table, string $message): array
    {
        if (! Schema::hasTable($table)) {
            return [false, "Tabel {$table} belum ada — jalankan php artisan migrate."];
        }

        return $model::where('is_active', true)->exists() ? [true, null] : [false, $message];
    }

    /**
     * Satu pengecekan yang error tidak boleh merusak halaman admin.
     */
    private function safely(callable $check): array
    {
        try {
            return $check();
        } catch (Throwable $e) {
            report($e);

            return [false, 'Tidak bisa diperiksa: ' . $e->getMessage()];
        }
    }
}
