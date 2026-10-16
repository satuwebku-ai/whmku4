<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi skema database lama (mis. hasil restore backup) dengan kolom dan
 * tabel yang dulu ditambahkan migrasi `add_*` / `create_*` terpisah
 * (2026_10_01 ... 2026_10_07), sebelum semuanya dilebur ke migrasi tabel
 * induknya.
 *
 * Mengapa perlu: migrasi induk (mis. create_clients_table) sudah tercatat
 * "selesai" di tabel `migrations` milik backup lama, jadi `php artisan migrate`
 * tidak akan menjalankannya lagi -- kolom tambahannya tidak akan pernah dibuat.
 *
 * Sifat:
 *  - Idempoten: tiap langkah memeriksa dulu (hasTable / hasColumn / tipe kolom)
 *    dan tidak melakukan apa pun bila sudah lengkap. Instalasi baru tidak
 *    terpengaruh.
 *  - Hanya MENAMBAH / melonggarkan (enum -> string, NOT NULL -> NULL); tidak
 *    menghapus kolom atau data.
 *  - Definisi kolom di sini harus sama dengan migrasi induknya.
 *    SchemaRepairTest menjaganya: pada skema hasil migrate, run() harus
 *    mengembalikan daftar kosong.
 *
 * Dipanggil oleh `lumora:sync-migrations` (otomatis setelah `lumora:restore`).
 */
class SchemaRepair
{
    /** @var list<string> */
    private array $log = [];

    /** Tabel yang AKAN dibuat -- hanya terisi saat dry-run, agar langkah berikutnya melapor dengan benar. */
    private array $virtual = [];

    private function __construct(private readonly bool $dry)
    {
    }

    /**
     * @return list<string> deskripsi tiap perbaikan (yang dilakukan, atau yang akan dilakukan bila $dryRun)
     */
    public static function run(bool $dryRun = false): array
    {
        $repair = new self($dryRun);

        // Urutan penting: tabel dulu, baru kolom yang bergantung padanya.
        $repair->productTypes();
        $repair->mailTables();
        $repair->missingColumns();
        $repair->columnTypes();
        $repair->serverGroupPivot();

        return $repair->log;
    }

    // ── Tabel ────────────────────────────────────────────────────────

    /** product_types + jenis bawaan (dulu migrasi 2026_10_07_000087_create_product_types_table). */
    private function productTypes(): void
    {
        if (! Schema::hasTable('product_groups') || Schema::hasTable('product_types')) {
            return;
        }

        $this->note('buat tabel product_types (+ jenis bawaan hosting & vps)');

        if ($this->dry) {
            $this->virtual[] = 'product_types';

            return;
        }

        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name');
            $table->string('kind', 20)->default('hosting');
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->default('#4f46e5');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('product_types')->insert([
            [
                'slug' => 'hosting', 'name' => 'Hosting (cPanel/WHM)', 'kind' => 'hosting',
                'icon' => 'fa-server', 'color' => '#4f46e5', 'sort_order' => 1,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'vps', 'name' => 'VPS / Cloud Server', 'kind' => 'vps',
                'icon' => 'fa-cloud', 'color' => '#059669', 'sort_order' => 2,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    /**
     * inbound_emails, mail_threads, mail_messages, mail_templates. Definisinya
     * tidak diduplikasi: migrasi Inbox Email sendiri idempoten (hanya membuat
     * tabel yang belum ada), jadi cukup dijalankan.
     */
    private function mailTables(): void
    {
        $missing = array_values(array_filter(
            ['inbound_emails', 'mail_threads', 'mail_messages', 'mail_templates'],
            fn (string $table) => ! Schema::hasTable($table)
        ));

        if ($missing === []) {
            return;
        }

        // Kunci asing tabel-tabel ini mengarah ke tabel di bawah; tanpanya
        // `migrate` biasa yang akan membuat semuanya pada gilirannya.
        foreach (['clients', 'chat_conversations', 'admins'] as $dependency) {
            if (! Schema::hasTable($dependency)) {
                return;
            }
        }

        $this->note('buat tabel Inbox Email: '.implode(', ', $missing));

        if ($this->dry) {
            array_push($this->virtual, ...$missing);

            return;
        }

        $migration = require database_path('migrations/2026_01_01_000074_create_mail_threads_tables.php');
        $migration->up();
    }

    /** server_group_server + salin keanggotaan dari kolom lama servers.server_group_id. */
    private function serverGroupPivot(): void
    {
        if (! Schema::hasTable('servers') || ! Schema::hasTable('server_groups') || Schema::hasTable('server_group_server')) {
            return;
        }

        $this->note('buat tabel server_group_server (+ salin keanggotaan dari servers.server_group_id)');

        if ($this->dry) {
            $this->virtual[] = 'server_group_server';

            return;
        }

        Schema::create('server_group_server', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_group_id')->constrained('server_groups')->cascadeOnDelete();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['server_group_id', 'server_id']);
        });

        if (! Schema::hasColumn('servers', 'server_group_id')) {
            return;
        }

        $now = now();

        DB::table('servers')
            ->whereNotNull('server_group_id')
            ->get(['id', 'server_group_id', 'priority'])
            ->each(function ($server) use ($now) {
                DB::table('server_group_server')->insert([
                    'server_group_id' => $server->server_group_id,
                    'server_id'       => $server->id,
                    'priority'        => $server->priority ?? 10,
                    'is_active'       => true,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            });
    }

    // ── Kolom ────────────────────────────────────────────────────────

    private function missingColumns(): void
    {
        $this->addColumns('clients', [
            'pending_email'           => fn (Blueprint $t) => $t->string('pending_email')->nullable(),
            'pending_email_code_hash' => fn (Blueprint $t) => $t->string('pending_email_code_hash')->nullable(),
            'pending_email_expires_at' => fn (Blueprint $t) => $t->timestamp('pending_email_expires_at')->nullable(),
            'pending_email_attempts'  => fn (Blueprint $t) => $t->unsignedTinyInteger('pending_email_attempts')->default(0),
            'password_otp_enabled'    => fn (Blueprint $t) => $t->boolean('password_otp_enabled')->default(false),
            'password_set_by_user'    => fn (Blueprint $t) => $t->boolean('password_set_by_user')->default(true),
        ], [
            // Akun login Google punya password acak yang tidak pernah diatur klien.
            'password_set_by_user' => function () {
                if (Schema::hasColumn('clients', 'google_id')) {
                    DB::table('clients')->whereNotNull('google_id')->update(['password_set_by_user' => false]);
                }
            },
        ]);

        $this->addColumns('hosting_accounts', [
            'pending_upgrade_price'       => fn (Blueprint $t) => $t->decimal('pending_upgrade_price', 12, 2)->nullable(),
            'panel_suspend_error'         => fn (Blueprint $t) => $t->text('panel_suspend_error')->nullable(),
            'credentials_sent_at'         => fn (Blueprint $t) => $t->timestamp('credentials_sent_at')->nullable(),
            'credentials_email_failed_at' => fn (Blueprint $t) => $t->timestamp('credentials_email_failed_at')->nullable(),
        ]);

        $this->addColumns('servers', [
            'priority'       => fn (Blueprint $t) => $t->unsignedSmallInteger('priority')->default(10),
            'is_maintenance' => fn (Blueprint $t) => $t->boolean('is_maintenance')->default(false),
        ]);

        $this->addColumns('server_groups', [
            'selection_mode' => fn (Blueprint $t) => $t->string('selection_mode', 30)->default('least_accounts'),
            'is_active'      => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
        ]);

        if ($this->has('server_groups')) {
            $this->addColumns('products', [
                'server_group_id' => fn (Blueprint $t) => $t->foreignId('server_group_id')->nullable()
                    ->constrained('server_groups')->nullOnDelete(),
            ]);
        }

        if ($this->has('product_types')) {
            $this->addColumns('product_groups', [
                'product_type_id' => fn (Blueprint $t) => $t->foreignId('product_type_id')->nullable()
                    ->constrained('product_types')->nullOnDelete(),
            ], [
                // Kategori yang sudah ada diarahkan ke jenis bawaan sesuai type lamanya.
                'product_type_id' => function () {
                    // Instalasi baru tidak punya kolom `type` (tidak ada yang perlu dipetakan).
                    if (! Schema::hasColumn('product_groups', 'type')) {
                        return;
                    }

                    $ids = DB::table('product_types')->pluck('id', 'slug');

                    if (isset($ids['vps'])) {
                        DB::table('product_groups')->where('type', 'vps')->update(['product_type_id' => $ids['vps']]);
                    }

                    if (isset($ids['hosting'])) {
                        DB::table('product_groups')
                            ->where(fn ($q) => $q->where('type', 'hosting')->orWhereNull('type'))
                            ->update(['product_type_id' => $ids['hosting']]);
                    }
                },
            ]);
        }

        $this->addColumns('tickets', [
            'idle_prompted_at' => fn (Blueprint $t) => $t->timestamp('idle_prompted_at')->nullable(),
        ]);

        $this->addColumns('chat_conversations', [
            'idle_prompted_at' => fn (Blueprint $t) => $t->timestamp('idle_prompted_at')->nullable(),
        ]);

        $this->addColumns('chat_messages', [
            'kind' => fn (Blueprint $t) => $t->string('kind', 30)->nullable(),
        ]);

        $this->addColumns('ai_chat_usages', [
            'kind' => fn (Blueprint $t) => $t->string('kind', 20)->default('bot'),
        ]);

        $mailThreads = [
            'idle_prompted_at' => fn (Blueprint $t) => $t->timestamp('idle_prompted_at')->nullable(),
        ];

        if (Schema::hasTable('chat_conversations')) {
            $mailThreads['chat_conversation_id'] = fn (Blueprint $t) => $t->foreignId('chat_conversation_id')->nullable()
                ->constrained('chat_conversations')->nullOnDelete();
        }

        $this->addColumns('mail_threads', $mailThreads);

        $this->addColumns('mail_messages', [
            'is_auto' => fn (Blueprint $t) => $t->boolean('is_auto')->default(false),
        ]);

        $this->addColumns('mail_templates', [
            'category'   => fn (Blueprint $t) => $t->string('category', 60)->nullable(),
            'is_active'  => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
            'use_for_ai' => fn (Blueprint $t) => $t->boolean('use_for_ai')->default(false),
        ]);
    }

    /**
     * Enum yang dulu dibuka menjadi string, dan kolom yang dulu NOT NULL
     * menjadi boleh kosong. Hanya melonggarkan -- data yang ada tetap valid.
     */
    private function columnTypes(): void
    {
        // Kanal baru ("email") tidak ada di enum lama; MySQL strict menolaknya.
        if ($this->isEnum('chat_conversations', 'channel')) {
            $this->change('chat_conversations.channel: enum -> string(20)', 'chat_conversations',
                fn (Blueprint $t) => $t->string('channel', 20)->default('web')->change());
        }

        // Jenis mutasi baru (mis. topup_reversal) tidak ada di enum lama.
        if ($this->isEnum('credits', 'type')) {
            $this->change('credits.type: enum -> string(32)', 'credits',
                fn (Blueprint $t) => $t->string('type', 32)->change());
        }

        // Draf AI untuk email biasa tidak punya percakapan chat.
        $usage = $this->column('ai_chat_usages', 'chat_conversation_id');
        if ($usage && ($usage['nullable'] ?? true) === false) {
            $this->change('ai_chat_usages.chat_conversation_id: NOT NULL -> NULL', 'ai_chat_usages',
                fn (Blueprint $t) => $t->unsignedBigInteger('chat_conversation_id')->nullable()->change());
        }
    }

    // ── Pembantu ─────────────────────────────────────────────────────

    /**
     * Tambahkan kolom yang belum ada pada tabel yang SUDAH ada.
     *
     * @param  array<string, callable(Blueprint): mixed>  $columns   nama kolom => definisi
     * @param  array<string, callable(): void>  $afterAdded  dijalankan setelah kolom itu ditambahkan
     */
    private function addColumns(string $table, array $columns, array $afterAdded = []): void
    {
        // Tabel yang baru akan dibuat (virtual) sudah lengkap dari awal.
        if (! Schema::hasTable($table)) {
            return;
        }

        $missing = array_filter($columns, fn ($define, string $name) => ! Schema::hasColumn($table, $name), ARRAY_FILTER_USE_BOTH);

        if ($missing === []) {
            return;
        }

        foreach (array_keys($missing) as $name) {
            $this->note("tambah kolom {$table}.{$name}");
        }

        if ($this->dry) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing) {
            foreach ($missing as $define) {
                $define($blueprint);
            }
        });

        foreach (array_intersect_key($afterAdded, $missing) as $callback) {
            $callback();
        }
    }

    private function change(string $description, string $table, callable $define): void
    {
        $this->note("ubah kolom {$description}");

        if (! $this->dry) {
            Schema::table($table, function (Blueprint $blueprint) use ($define) {
                $define($blueprint);
            });
        }
    }

    private function has(string $table): bool
    {
        return Schema::hasTable($table) || in_array($table, $this->virtual, true);
    }

    /** @return array<string, mixed>|null */
    private function column(string $table, string $name): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        foreach (Schema::getColumns($table) as $column) {
            if (strtolower((string) $column['name']) === strtolower($name)) {
                return $column;
            }
        }

        return null;
    }

    private function isEnum(string $table, string $name): bool
    {
        $column = $this->column($table, $name);

        return $column !== null && strtolower((string) ($column['type_name'] ?? '')) === 'enum';
    }

    private function note(string $message): void
    {
        $this->log[] = $message;
    }
}
