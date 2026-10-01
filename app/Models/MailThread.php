<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satu rangkaian email (thread) di Inbox Email admin.
 */
class MailThread extends Model
{
    public const NO_SUBJECT = '(tanpa subjek)';

    protected $fillable = [
        'subject', 'contact_email', 'contact_name', 'client_id',
        'status', 'unread_count', 'last_message_at', 'chat_conversation_id',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class)->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(MailMessage::class)->latestOfMany();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function chatConversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->contact_name ?: ($this->client?->name ?: $this->contact_email);
    }

    public function getInitialsAttribute(): string
    {
        $kata = preg_split('/\s+/', trim($this->display_name));

        return strtoupper(mb_substr($kata[0], 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : ''));
    }

    /**
     * Token di subjek email keluar; membuat balasan pelanggan kembali ke
     * thread ini walau aplikasi email mereka membuang header threading.
     */
    public function token(): string
    {
        return '[MAIL-' . $this->id . ']';
    }

    /**
     * Subjek tanpa awalan Re:/Fwd: dan tanpa token, untuk mencocokkan thread.
     */
    public static function normalizeSubject(string $subject): string
    {
        $s = preg_replace('/\s*\[MAIL-\d+\]\s*/i', ' ', $subject) ?? $subject;
        $s = preg_replace('/^\s*((re|fwd?|aw|sv)\s*(\[\d+\])?\s*:\s*)+/i', '', $s) ?? $s;
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);

        return $s !== '' ? $s : self::NO_SUBJECT;
    }
}
