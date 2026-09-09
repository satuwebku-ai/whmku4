<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemetaan "ekstensi domain -> persyaratan berkas".
 *
 * Ekstensi disimpan sebagai TEKS, bukan foreign key ke tabel tlds.
 * Alasannya: satu ekstensi sekarang bisa punya beberapa baris tld (satu
 * per registrar), sedangkan syarat dokumen ditentukan REGISTRY, bukan
 * registrar -- ".co.id" lewat DNAMA maupun Liqu.id syaratnya sama.
 */
class DocumentRequirementTld extends Model
{
    protected $table = 'document_requirement_tld';

    protected $fillable = ['document_requirement_id', 'extension'];

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class, 'document_requirement_id');
    }
}
