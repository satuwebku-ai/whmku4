<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'group'];

    /**
     * Titik awal: satu permission per modul yang sudah ada di
     * Admin::MODULES, supaya konsisten dengan middleware `module:xxx`
     * yang sudah dipakai di seluruh routes/admin.php. Bukan berarti
     * permission dibatasi hanya level-modul selamanya -- tabel ini bisa
     * ditambah permission granular per-aksi kapan saja tanpa migration
     * baru (tinggal insert baris), PermissionMiddleware akan langsung
     * mengenalinya.
     */
    public static function defaultSlugs(): array
    {
        return array_keys(Admin::MODULES);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
