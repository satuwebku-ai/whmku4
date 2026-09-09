<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number', 'client_id', 'assigned_to', 'subject', 'department',
        'priority', 'status', 'hosting_account_id', 'domain_id', 'invoice_id',
        'last_reply_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = static::generateNumber();
            }

            $ticket->last_reply_at ??= now();
        });
    }

    public static function generateNumber(): string
    {
        $year = now()->year;
        $last = static::whereYear('created_at', $year)->orderByDesc('id')->first();
        $next = $last ? ((int) Str::afterLast($last->ticket_number, '-') + 1) : 1;

        return "TKT-{$year}-" . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->orderBy('created_at');
    }

    /**
     * Balasan yang terlihat klien (tanpa catatan internal staf).
     */
    public function publicReplies(): HasMany
    {
        return $this->replies()->where('is_internal_note', false);
    }

    public function hostingAccount(): BelongsTo
    {
        return $this->belongsTo(HostingAccount::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Tiket yang butuh perhatian staf.
     */
    public function needsAttention(): bool
    {
        return in_array($this->status, ['open', 'customer_reply'], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Baru',
            'answered' => 'Dijawab',
            'customer_reply' => 'Balasan Klien',
            'closed' => 'Ditutup',
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'open' => 'pending',
            'customer_reply' => 'overdue',
            'answered' => 'active',
            'closed' => 'inactive',
            default => 'pending',
        };
    }

    public function getPriorityBadgeAttribute(): string
    {
        return match ($this->priority) {
            'urgent', 'high' => 'suspended',
            'medium' => 'pending',
            default => 'inactive',
        };
    }
}
