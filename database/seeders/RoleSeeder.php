<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissionGroups = [
            'sales' => ['sales.view' => 'Lihat penjualan', 'products.manage' => 'Kelola produk', 'orders.manage' => 'Kelola order', 'coupons.manage' => 'Kelola kupon'],
            'billing' => ['billing.view' => 'Lihat billing', 'invoices.manage' => 'Kelola invoice', 'payments.manage' => 'Kelola pembayaran', 'gateways.manage' => 'Kelola payment gateway'],
            'services' => ['clients.manage' => 'Kelola klien', 'services.manage' => 'Kelola layanan', 'domains.manage' => 'Kelola domain'],
            'infrastructure' => ['servers.manage' => 'Kelola server', 'provisioning.manage' => 'Kelola provisioning', 'registrars.manage' => 'Kelola registrar'],
            'support' => ['tickets.manage' => 'Kelola tiket', 'chat.manage' => 'Kelola live chat'],
            'content' => ['content.manage' => 'Kelola konten', 'announcements.manage' => 'Kelola pengumuman', 'templates.manage' => 'Kelola template'],
            'system' => ['settings.manage' => 'Kelola pengaturan', 'admins.manage' => 'Kelola admin', 'activity.view' => 'Lihat log aktivitas'],
        ];

        foreach ($permissionGroups as $group => $items) {
            foreach ($items as $slug => $name) {
                Permission::updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
            }
        }

        $allPermissionIds = Permission::query()->pluck('id')->all();
        $byGroup = Permission::query()->get()->groupBy('group');

        foreach (Role::ROLES as $slug => $name) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => Role::DESCRIPTIONS[$slug] ?? 'Peran kompatibilitas sistem lama.', 'is_system' => true]
            );

            $groups = match ($slug) {
                'superadmin' => array_keys($permissionGroups),
                'administrator' => ['sales', 'billing', 'services', 'infrastructure', 'support', 'content'],
                'finance', 'billing' => ['billing'],
                'domain_manager' => ['services', 'infrastructure'],
                'hosting_manager', 'developer', 'devops' => ['services', 'infrastructure', 'system'],
                'support' => ['services', 'support'],
                'marketing' => ['sales', 'content'],
                'auditor' => ['system'],
                default => ['services', 'support'],
            };

            $ids = $slug === 'superadmin'
                ? $allPermissionIds
                : collect($groups)->flatMap(fn ($group) => ($byGroup->get($group) ?? collect())->pluck('id'))->unique()->values()->all();
            $role->permissions()->sync($ids);
        }
    }
}
