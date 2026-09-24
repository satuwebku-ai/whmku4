<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseCategory extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order'];

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeBase::class);
    }
}
