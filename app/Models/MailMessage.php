<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailMessage extends Model
{
    protected $fillable = [
        'mail_thread_id', 'direction', 'from_email', 'from_name', 'to_email',
        'subject', 'body', 'message_id', 'admin_id', 'attachments', 'is_auto',
    ];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'is_auto' => 'boolean'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MailThread::class, 'mail_thread_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === 'in';
    }
}
