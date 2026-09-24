<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CronJob extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'command', 'interval_minutes',
        'is_enabled', 'last_run_at', 'next_run_at', 'run_count',
        'last_status', 'last_output', 'last_duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /**
     * Daftar tugas bawaan aplikasi.
     *
     * Disimpan di kode (bukan hanya database) supaya tugas baru otomatis
     * muncul setelah update, dan supaya tidak ada baris di database yang
     * menunjuk ke perintah yang sudah tidak ada.
     *
     * Pilihan interval sengaja tidak sefleksibel ekspresi cron: tugas
     * penagihan yang salah setel bisa mengirim email berkali-kali sehari
     * ke seluruh pelanggan.
     */
    public const BUILT_IN = [
        'invoice_reminder' => [
            'name' => 'Pengingat Tagihan',
            'description' => 'Kirim email/WA pengingat sebelum dan sesudah jatuh tempo.',
            'command' => 'lumora:send-reminders',
            'interval_minutes' => 1440, // sekali sehari
        ],
        'mark_overdue' => [
            'name' => 'Tandai Invoice Lewat Tempo',
            'description' => 'Ubah status invoice yang melewati jatuh tempo menjadi "overdue".',
            'command' => 'lumora:mark-overdue',
            'interval_minutes' => 360, // 6 jam
        ],
        'suspend_overdue' => [
            'name' => 'Suspend Layanan Menunggak',
            'description' => 'Suspend hosting yang tagihannya lewat tempo melebihi batas toleransi.',
            'command' => 'lumora:suspend-overdue',
            'interval_minutes' => 1440,
        ],
        'clean_activity' => [
            'name' => 'Bersihkan Log Aktivitas',
            'description' => 'Hapus catatan aktivitas lama yang sudah dibaca.',
            'command' => 'lumora:clean-activity',
            'interval_minutes' => 10080, // seminggu
        ],
        'generate_renewal_invoices' => [
            'name' => 'Buat Invoice Perpanjangan',
            'description' => 'Terbitkan invoice perpanjangan H-7 untuk layanan yang akan jatuh tempo.',
            'command' => 'lumora:generate-renewal-invoices',
            'interval_minutes' => 1440,
        ],
        'expire_privacy' => [
            'name' => 'Kadaluwarsa ID Protection',
            'description' => 'Matikan ID Protection domain yang masa berlakunya habis dan tidak diperpanjang.',
            'command' => 'lumora:expire-privacy',
            'interval_minutes' => 1440,
        ],
        'reconcile_provisioning' => [
            'name' => 'Jaring Pengaman Provisioning',
            'description' => 'Perbaiki otomatis invoice lunas yang layanannya (hosting/domain) belum aktif.',
            'command' => 'lumora:reconcile-provisioning',
            'interval_minutes' => 180,
        ],
        'reconcile_billing' => [
            'name' => 'Rekonsiliasi Billing',
            'description' => 'Audit dan perbaiki gap deterministik antara payment, invoice, transaction ledger, dan top-up.',
            'command' => 'lumora:reconcile-billing --repair',
            'interval_minutes' => 60,
        ],
        'close_inactive_chats' => [
            'name' => 'Tutup Chat Tidak Aktif',
            'description' => 'Tutup otomatis percakapan live chat yang sudah lama tidak ada aktivitas.',
            'command' => 'lumora:close-inactive-chats',
            'interval_minutes' => 5,
        ],
        'backup' => [
            'name' => 'Backup Otomatis',
            'description' => 'Cadangkan database dan file upload ke ZIP (opsional ikut unggah ke Google Drive).',
            'command' => 'lumora:backup',
            'interval_minutes' => 1440,
        ],
        'charge_hourly_usage' => [
            'name' => 'Potong Saldo Layanan Deposit',
            'description' => 'Potong saldo klien untuk layanan yang ditagih per jam (mis. VM/VPS), suspend otomatis kalau saldo habis.',
            'command' => 'lumora:charge-hourly-usage',
            'interval_minutes' => 60,
        ],
        'registrar_backup_customers' => [
            'name' => 'Backup Data Registrar',
            'description' => 'Cadangkan data pelanggan dari API registrar ke CSV (pelengkap backup database, bukan pengganti).',
            'command' => 'registrar:backup-customers',
            'interval_minutes' => 10080, // seminggu
        ],
    ];

    /**
     * Pilihan interval yang boleh dipakai, dalam menit.
     */
    public const INTERVALS = [
        5 => 'Tiap 5 menit',
        15 => 'Tiap 15 menit',
        30 => 'Tiap 30 menit',
        60 => 'Tiap jam',
        360 => 'Tiap 6 jam',
        720 => 'Tiap 12 jam',
        1440 => 'Sekali sehari',
        10080 => 'Sekali seminggu',
    ];

    /**
     * Buat baris untuk tugas bawaan yang belum ada di database.
     * Aman dipanggil berulang kali.
     */
    public static function syncBuiltIn(): int
    {
        $created = 0;

        foreach (self::BUILT_IN as $key => $config) {
            $job = static::firstOrNew(['key' => $key]);

            if (! $job->exists) {
                $job->fill($config + ['is_enabled' => true, 'next_run_at' => now()]);
                $job->save();
                $created++;
                continue;
            }

            // Nama/deskripsi/perintah selalu mengikuti kode, tapi interval
            // dan status aktif adalah keputusan admin — jangan ditimpa.
            $job->update([
                'name' => $config['name'],
                'description' => $config['description'],
                'command' => $config['command'],
            ]);
        }

        return $created;
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_enabled', true)
            ->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()));
    }

    public function getIntervalLabelAttribute(): string
    {
        return self::INTERVALS[$this->interval_minutes] ?? "Tiap {$this->interval_minutes} menit";
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->last_status) {
            'success' => 'active',
            'failed' => 'suspended',
            'running' => 'pending',
            default => 'inactive',
        };
    }

    public function isOverdue(): bool
    {
        return $this->is_enabled
            && $this->next_run_at
            && $this->next_run_at->lt(now()->subMinutes(30));
    }

    /**
     * Dipanggil langsung oleh perintah artisan di akhir eksekusinya, bukan
     * hanya oleh lumora:cron. Ini membuat status panel tetap akurat saat
     * command dijalankan manual dari Console admin atau SSH.
     *
     * Aman dipanggil untuk command yang tidak terdaftar di BUILT_IN
     * (tidak melakukan apa-apa kalau tidak ketemu), supaya tidak
     * melempar error kalau ada command lain yang ikut memanggil ini.
     */
    public static function recordExecution(string $command, bool $success, ?string $output = null): void
    {
        static::syncBuiltIn();

        $job = static::where('command', $command)->first();

        if (! $job) {
            return;
        }

        $job->update([
            'last_status' => $success ? 'success' : 'failed',
            'last_output' => $output ? mb_substr($output, 0, 2000) : null,
            'last_run_at' => now(),
            'next_run_at' => now()->addMinutes($job->interval_minutes),
            'run_count' => $job->run_count + 1,
        ]);
    }
}
