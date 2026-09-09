<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan aktivitas aplikasi untuk admin: order masuk, pembayaran,
 * tiket baru, klien mendaftar, dan kejadian sistem lain.
 *
 * Disimpan di database (bukan hanya log file) supaya bisa ditampilkan
 * sebagai notifikasi di panel dan ditandai sudah dibaca.
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'type', 'title', 'description', 'link', 'icon', 'level',
        'client_id', 'admin_id', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Catat satu aktivitas.
     *
     * Sengaja tidak melempar exception: kegagalan mencatat log tidak boleh
     * membatalkan transaksi bisnis yang sedang berjalan.
     */
    public static function record(
        string $type,
        string $title,
        ?string $description = null,
        ?string $link = null,
        string $level = 'info',
        ?int $clientId = null,
    ): ?self {
        try {
            return static::create([
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'link' => $link,
                'level' => $level,
                'icon' => static::iconFor($type),
                'client_id' => $clientId,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat aktivitas: ' . $e->getMessage());

            return null;
        }
    }

    private static function iconFor(string $type): string
    {
        return match ($type) {
            'order' => 'fa-cart-shopping',
            'payment' => 'fa-money-bill-wave',
            'invoice' => 'fa-file-invoice',
            'ticket' => 'fa-comments',
            'client' => 'fa-user-plus',
            'domain' => 'fa-globe',
            'service' => 'fa-server',
            default => 'fa-circle-info',
        };
    }

    public function getLevelClassAttribute(): string
    {
        return match ($this->level) {
            'success' => 'bg-emerald-100 text-emerald-600',
            'warning' => 'bg-amber-100 text-amber-600',
            'danger' => 'bg-rose-100 text-rose-600',
            default => 'bg-indigo-100 text-indigo-600',
        };
    }
}
