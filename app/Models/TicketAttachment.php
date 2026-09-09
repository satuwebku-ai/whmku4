<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    protected $fillable = [
        'ticket_reply_id', 'path', 'original_name', 'mime_type', 'size',
    ];

    public function reply(): BelongsTo
    {
        return $this->belongsTo(TicketReply::class, 'ticket_reply_id');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    public function isImage(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'image/');
    }

    public function getSizeLabelAttribute(): ?string
    {
        if (! $this->size) {
            return null;
        }

        return match (true) {
            $this->size >= 1048576 => number_format($this->size / 1048576, 1) . ' MB',
            $this->size >= 1024 => number_format($this->size / 1024, 0) . ' KB',
            default => $this->size . ' B',
        };
    }
}
