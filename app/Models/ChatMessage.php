<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_conversation_id', 'sender', 'admin_id', 'message',
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
        return $this->attachment_path ? asset('storage/' . $this->attachment_path) : null;
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
            'time' => $this->created_at->format('H:i'),
        ];
    }
}
