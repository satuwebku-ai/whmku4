<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Mengisi tabel roles & permissions (12 role Master Blueprint + 7
 * permission setara modul yang sudah ada), lalu menyinkronkan SEMUA admin
 * yang sudah ada ke role yang setara berdasarkan kolom lama `admins.role`
 * + `admins.permissions`. Aman dijalankan berulang kali (idempotent).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::ROLES as $slug => $name) {
            Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => Role::DESCRIPTIONS[$slug] ?? null,
                    'is_system' => $slug === 'superadmin',
                ]
            );
        }

        foreach (Admin::MODULES as $slug => $label) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $label, 'group' => 'module']
            );
        }

        // superadmin & administrator dapat semua permission modul.
        $allPermissionIds = Permission::pluck('id');
        Role::whereIn('slug', ['superadmin', 'administrator'])->each(
            fn (Role $role) => $role->permissions()->sync($allPermissionIds)
        );

        // finance & billing -> modul billing.
        $this->grant('finance', ['billing']);
        $this->grant('billing', ['billing']);
        // domain_manager & hosting_manager -> layanan + infrastruktur.
        $this->grant('domain_manager', ['services', 'infrastructure']);
        $this->grant('hosting_manager', ['services', 'infrastructure']);
        // support -> dukungan + layanan (perlu lihat data klien).
        $this->grant('support', ['support', 'services']);
        // marketing -> konten + penjualan (kupon, halaman promo).
        $this->grant('marketing', ['content', 'sales']);
        // developer & devops -> infrastruktur + sistem.
        $this->grant('developer', ['infrastructure', 'system']);
        $this->grant('devops', ['infrastructure', 'system']);
        // auditor & viewer sengaja TIDAK diberi permission modul apa pun
        // di sini -- keduanya untuk lihat log/laporan, bukan mengelola
        // modul operasional; beri akses spesifik manual kalau diperlukan.

        // Sinkronkan semua admin yang sudah ada ke role yang setara.
        Admin::all()->each(fn (Admin $admin) => $admin->syncRoleFromLegacyColumn());
    }

    private function grant(string $roleSlug, array $moduleSlugs): void
    {
        $role = Role::where('slug', $roleSlug)->first();

        if (! $role) {
            return;
        }

        $ids = Permission::whereIn('slug', $moduleSlugs)->pluck('id');
        $role->permissions()->sync($ids);
    }
}
