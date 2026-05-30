<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // 1. Admin user
        // ============================================================
        $admin = User::create([
            'name'         => 'Admin Santap',
            'email'        => 'admin@santap.app',
            'password'     => bcrypt('password'),
            'is_superadmin' => true,
        ]);

        // ============================================================
        // 2. Owner user
        // ============================================================
        $owner = User::create([
            'name' => 'Budi Owner',
            'email' => 'owner@santap.app',
            'password' => bcrypt('password'),
        ]);

        // ============================================================
        // 3. Cashier user
        // ============================================================
        $cashier = User::create([
            'name' => 'Sari Kasir',
            'email' => 'cashier@santap.app',
            'password' => bcrypt('password'),
        ]);

        // ============================================================
        // 4. Kitchen user
        // ============================================================
        $kitchen = User::create([
            'name' => 'Andi Kitchen',
            'email' => 'kitchen@santap.app',
            'password' => bcrypt('password'),
        ]);

        // ============================================================
        // 5. Organization
        // ============================================================
        $org = Organization::create([
            'name' => 'Warung Santap Demo',
            'slug' => 'warung-santap-demo',
        ]);

        // ============================================================
        // 6. Members
        // ============================================================
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'owner']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $cashier->id, 'role' => 'cashier']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $kitchen->id, 'role' => 'kitchen']);

        // ============================================================
        // 7. Dining Tables
        // ============================================================
        foreach (['Meja 1', 'Meja 2', 'Meja 3', 'VIP 1'] as $name) {
            DiningTable::create([
                'organization_id' => $org->id,
                'name' => $name,
                'qr_token' => Str::random(32),
            ]);
        }

        // ============================================================
        // 8. Menu Tree
        // ============================================================

        // --- Nasi Goreng ---
        $nasiGoreng = Menu::create([
            'organization_id' => $org->id,
            'type' => 'product',
            'name' => 'Nasi Goreng',
            'price' => 25000,
            'sort_order' => 1,
        ]);

        $levelPedas = Menu::create([
            'organization_id' => $org->id,
            'parent_id' => $nasiGoreng->id,
            'type' => 'variant_group',
            'name' => 'Level Pedas',
            'sort_order' => 1,
        ]);

        Menu::create(['organization_id' => $org->id, 'parent_id' => $levelPedas->id, 'type' => 'variant', 'name' => 'Tidak Pedas', 'sort_order' => 1]);
        Menu::create(['organization_id' => $org->id, 'parent_id' => $levelPedas->id, 'type' => 'variant', 'name' => 'Sedang', 'sort_order' => 2]);
        Menu::create(['organization_id' => $org->id, 'parent_id' => $levelPedas->id, 'type' => 'variant', 'name' => 'Pedas', 'sort_order' => 3]);

        $addon1 = Menu::create([
            'organization_id' => $org->id,
            'parent_id' => $nasiGoreng->id,
            'type' => 'addon_group',
            'name' => 'Tambahan',
            'sort_order' => 2,
        ]);

        Menu::create(['organization_id' => $org->id, 'parent_id' => $addon1->id, 'type' => 'addon', 'name' => 'Telur Ceplok', 'price' => 3000, 'sort_order' => 1]);
        Menu::create(['organization_id' => $org->id, 'parent_id' => $addon1->id, 'type' => 'addon', 'name' => 'Sosis', 'price' => 5000, 'sort_order' => 2]);
        Menu::create(['organization_id' => $org->id, 'parent_id' => $addon1->id, 'type' => 'addon', 'name' => 'Kerupuk', 'price' => 2000, 'sort_order' => 3]);

        // --- Mie Goreng ---
        $mieGoreng = Menu::create([
            'organization_id' => $org->id,
            'type' => 'product',
            'name' => 'Mie Goreng',
            'price' => 22000,
            'sort_order' => 2,
        ]);

        // --- Es Teh Manis ---
        Menu::create([
            'organization_id' => $org->id,
            'type' => 'product',
            'name' => 'Es Teh Manis',
            'price' => 5000,
            'sort_order' => 3,
        ]);

        // --- Es Jeruk ---
        Menu::create([
            'organization_id' => $org->id,
            'type' => 'product',
            'name' => 'Es Jeruk',
            'price' => 8000,
            'sort_order' => 4,
        ]);

        // --- Kopi ---
        Menu::create([
            'organization_id' => $org->id,
            'type' => 'product',
            'name' => 'Kopi Hitam',
            'price' => 7000,
            'sort_order' => 5,
        ]);

        $this->command->info('✅ Seeder selesai: 4 users, 1 org, 4 meja, 5 products (1 dengan variant+addon)');

        // Tenant kedua: Kobesah Godean
        $this->call(KobesahGodeanSeeder::class);
    }
}
