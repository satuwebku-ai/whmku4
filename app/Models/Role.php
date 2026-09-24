<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role RBAC per Master Blueprint (12 role staf: superadmin, administrator,
 * finance, billing, domain_manager, hosting_manager, support, marketing,
 * developer, devops, auditor, viewer).
 *
 * SENGAJA berjalan BERDAMPINGAN dengan sistem lama (kolom `admins.role`
 * string + `admins.permissions` json per-modul), bukan menggantikannya:
 * sistem lama mendukung override modul KUSTOM per-admin individual (mis.
 * "staff A boleh akses billing meski role staff biasanya tidak"), sesuatu
 * yang tidak bisa direpresentasikan kalau akses HANYA ditentukan oleh
 * role. Middleware `module:xxx`/`role:xxx` yang dipakai di seluruh
 * routes/admin.php tetap membaca sistem lama itu supaya tidak ada
 * satupun dari puluhan definisi route yang perlu diubah atau berisiko
 * rusak. Tabel roles/permissions ini disinkron otomatis dari kolom lama
 * lewat Admin::booted() (lihat komentar di sana) dan dari sini
 * blueprint-compliant hasRole()/hasPermission() tersedia untuk kode baru
 * yang mau memakai pola RBAC berbasis tabel.
 */
class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * 12 role staf sesuai Master Blueprint. Dipakai RoleSeeder saat
     * migrasi awal dan oleh Admin::syncRoleFromLegacyColumn() untuk
     * memetakan nilai lama `admins.role` ('superadmin'/'admin'/'staff')
     * ke slug role yang setara di tabel ini.
     */
    public const ROLES = [
        'superadmin'      => 'Super Admin',
        'administrator'   => 'Administrator',
        'finance'         => 'Finance',
        'billing'         => 'Billing',
        'domain_manager'  => 'Domain Manager',
        'hosting_manager' => 'Hosting Manager',
        'support'         => 'Support',
        'marketing'       => 'Marketing',
        'developer'       => 'Developer',
        'devops'          => 'DevOps',
        'auditor'         => 'Auditor',
        'viewer'          => 'Viewer',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class);
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }
}
