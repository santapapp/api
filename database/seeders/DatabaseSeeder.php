<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Run Roles and Permissions Seeder
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Create Global Administrator
        $admin = User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Santap Super Admin',
            'password' => bcrypt('password'),
            'phone' => '081234567890',
            'status' => 'active',
        ]);

        // Assign global administrator role (team ID is null for global)
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $admin->syncRoles(['administrator']);

        // 3. Create Resto Owner, Cashier, and Kitchen for testing
        $owner = User::firstOrCreate([
            'email' => 'owner@santap.com',
        ], [
            'name' => 'Resto Owner',
            'password' => bcrypt('password'),
            'phone' => '081234567891',
            'status' => 'active',
        ]);

        $cashier = User::firstOrCreate([
            'email' => 'cashier@santap.com',
        ], [
            'name' => 'Resto Cashier',
            'password' => bcrypt('password'),
            'phone' => '081234567892',
            'status' => 'active',
        ]);

        $kitchen = User::firstOrCreate([
            'email' => 'kitchen@santap.com',
        ], [
            'name' => 'Resto Kitchen',
            'password' => bcrypt('password'),
            'phone' => '081234567893',
            'status' => 'active',
        ]);

        // 4. Create Demo Organization: Warung Padang Sekeco
        $organization = \App\Models\Organization::firstOrCreate([
            'slug' => 'warung-padang-sekeco',
        ], [
            'name' => 'Warung Padang Sekeco',
            'code' => 'WPS',
            'email' => 'padang@sekeco.id',
            'phone' => '081234567890',
            'address' => 'Jl. Jenderal Sudirman No. 123',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'country' => 'Indonesia',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'status' => \App\Enums\OrganizationStatus::Active,
            'created_by' => $admin->id,
        ]);

        // Attach users to organization
        if (!$organization->users()->where('user_id', $owner->id)->exists()) {
            $organization->users()->attach($owner->id, [
                'role_name' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }
        if (!$organization->users()->where('user_id', $cashier->id)->exists()) {
            $organization->users()->attach($cashier->id, [
                'role_name' => 'cashier',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }
        if (!$organization->users()->where('user_id', $kitchen->id)->exists()) {
            $organization->users()->attach($kitchen->id, [
                'role_name' => 'kitchen',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        // Assign Spatie roles inside organization
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $owner->assignRole('owner');
        $cashier->assignRole('cashier');
        $kitchen->assignRole('kitchen');

        // 5. Seed Menu Categories
        $makanan = \App\Models\MenuCategory::firstOrCreate([
            'organization_id' => $organization->id,
            'slug' => 'makanan',
        ], [
            'name' => 'Makanan',
        ]);

        $minuman = \App\Models\MenuCategory::firstOrCreate([
            'organization_id' => $organization->id,
            'slug' => 'minuman',
        ], [
            'name' => 'Minuman',
        ]);

        $cemilan = \App\Models\MenuCategory::firstOrCreate([
            'organization_id' => $organization->id,
            'slug' => 'cemilan',
        ], [
            'name' => 'Cemilan',
        ]);

        // 6. Seed Menus
        \App\Models\Menu::firstOrCreate([
            'organization_id' => $organization->id,
            'slug' => 'rendang-daging',
        ], [
            'menu_category_id' => $makanan->id,
            'name' => 'Rendang Daging',
            'price' => 25000,
            'status' => \App\Enums\MenuStatus::Active,
            'sku' => 'M001',
        ]);

        \App\Models\Menu::firstOrCreate([
            'organization_id' => $organization->id,
            'slug' => 'ayam-pop',
        ], [
            'menu_category_id' => $makanan->id,
            'name' => 'Ayam Pop',
            'price' => 22000,
            'status' => \App\Enums\MenuStatus::Active,
            'sku' => 'M002',
        ]);

        \App\Models\Menu::firstOrCreate([
            'organization_id' => $organization->id,
            'slug' => 'es-teh-manis',
        ], [
            'menu_category_id' => $minuman->id,
            'name' => 'Es Teh Manis',
            'price' => 5000,
            'status' => \App\Enums\MenuStatus::Active,
            'sku' => 'M003',
        ]);

        \App\Models\Menu::firstOrCreate([
            'organization_id' => $organization->id,
            'slug' => 'keripik-singkong',
        ], [
            'menu_category_id' => $cemilan->id,
            'name' => 'Keripik Singkong',
            'price' => 8000,
            'status' => \App\Enums\MenuStatus::Active,
            'sku' => 'M004',
        ]);

        // 7. Seed Dining Tables
        foreach (range(1, 4) as $num) {
            \App\Models\DiningTable::firstOrCreate([
                'organization_id' => $organization->id,
                'code' => 'T' . $num,
            ], [
                'name' => 'Meja ' . $num,
                'status' => \App\Enums\TableStatus::Available,
            ]);
        }

        // 8. Seed Demo Org: Kobesah Godean
        $this->call(KobesahGodeanSeeder::class);
    }
}
