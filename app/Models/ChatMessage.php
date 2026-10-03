<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public const KIND_IDLE_PROMPT = 'idle_prompt';
    public const REPLY_CONTINUE = 'Lanjut';
    public const REPLY_DECLINE = 'Tidak, terima kasih';

    protected $fillable = [
        'chat_conversation_id', 'sender', 'kind', 'admin_id', 'message',
        'attachment_path', 'attachment_name', 'attachment_mime', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function isImage(): bool
    {
        return $this->attachment_mime && str_starts_with($this->attachment_mime, 'image/');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? route('chat.attachment', $this) : null;
    }

    /**
     * Bentuk ringkas untuk dikirim ke widget lewat JSON.
     */
    public function toWidgetArray(): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'author' => $this->sender === 'admin' ? ($this->admin?->name ?: 'Tim Support') : null,
            'message' => $this->message,
            'attachment_url' => $this->attachment_url,
            'attachment_name' => $this->attachment_name,
            'is_image' => $this->isImage(),
            // Tombol pilihan cepat di widget (hanya untuk pertanyaan "mau lanjut?").
            'quick_replies' => $this->kind === self::KIND_IDLE_PROMPT
                ? [self::REPLY_CONTINUE, self::REPLY_DECLINE]
                : [],
            'time' => $this->created_at->format('H:i'),
        ];
    }
}
