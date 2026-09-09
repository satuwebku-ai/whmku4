<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'guest_token', 'client_id', 'name', 'email', 'phone', 'status', 'channel',
        'last_message_at', 'unread_for_admin', 'unread_for_user',
        'page_url', 'ip_address', 'assigned_admin_id', 'assigned_at', 'ticket_id',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'assigned_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * Belum dipegang staf mana pun DAN masih ada pesan klien yang belum
     * dibalas -- inilah yang jadi kandidat "antrian" untuk diambil alih
     * otomatis oleh staf yang baru selesai membalas percakapan lain.
     */
    public function scopeWaitingUnassigned(Builder $query): Builder
    {
        return $query->open()
            ->whereNull('assigned_admin_id')
            ->where('unread_for_admin', '>', 0);
    }

    /**
     * Nama yang ditampilkan ke admin. Klien terdaftar memakai nama akunnya;
     * pengunjung anonim diberi label yang jelas agar tidak tampak seperti
     * data yang hilang.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->client?->name
            ?: ($this->name ?: 'Pengunjung #' . $this->id);
    }

    public function getInitialsAttribute(): string
    {
        $nama = trim($this->display_name);
        $kata = preg_split('/\s+/', $nama);

        return strtoupper(mb_substr($kata[0], 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : ''));
    }
}
