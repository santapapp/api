<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Pastikan team_id null untuk membuat roles dan permissions global
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        // 1. Definisikan semua permission
        $permissions = [
            // Organisasi
            'organization.view',
            'organization.update',
            'organization.invite_user',
            'organization.manage_member',

            // Menu
            'menu.view',
            'menu.create',
            'menu.update',
            'menu.delete',

            // Kategori Menu
            'category.view',
            'category.create',
            'category.update',
            'category.delete',

            // Dining Table
            'table.view',
            'table.create',
            'table.update',
            'table.delete',
            'table.generate_qr',

            // Order
            'order.view',
            'order.create',
            'order.update_status',
            'order.cancel',

            // Kitchen
            'kitchen.view',
            'kitchen.update_order_status',

            // Bill
            'bill.view',
            'bill.open',
            'bill.close',
            'bill.cancel',

            // Payment
            'payment.view',
            'payment.create',
            'payment.refund',

            // Report & Audit
            'report.view',
            'report.export',
            'audit.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // 2. Buat Role Global
        Role::firstOrCreate([
            'name' => 'administrator',
            'guard_name' => 'web',
            'organization_id' => null,
        ]);

        // 3. Buat Role Organisasi
        $ownerRole = Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
            'organization_id' => null,
        ]);

        $cashierRole = Role::firstOrCreate([
            'name' => 'cashier',
            'guard_name' => 'web',
            'organization_id' => null,
        ]);

        $kitchenRole = Role::firstOrCreate([
            'name' => 'kitchen',
            'guard_name' => 'web',
            'organization_id' => null,
        ]);

        // 4. Sync Permissions ke Roles
        // Owner mendapatkan seluruh permission
        $ownerRole->syncPermissions($permissions);

        // Cashier mendapatkan permission operasional penjualan
        $cashierRole->syncPermissions([
            'menu.view',
            'category.view',
            'table.view',
            'order.view',
            'order.create',
            'order.cancel',
            'bill.view',
            'bill.open',
            'bill.close',
            'payment.view',
            'payment.create',
            'report.view',
        ]);

        // Kitchen hanya melihat order dan update status kitchen
        $kitchenRole->syncPermissions([
            'order.view',
            'kitchen.view',
            'kitchen.update_order_status',
        ]);
    }
}
