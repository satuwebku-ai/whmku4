<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Template balasan cepat (teks support/helpdesk) yang bisa dipilih admin
 * saat membalas email atau live chat.
 */
class MailTemplate extends Model
{
    protected $fillable = ['title', 'subject', 'body', 'sort'];
}
