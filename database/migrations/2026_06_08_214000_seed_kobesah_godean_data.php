<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Seed Users
        $ownerId = DB::table('users')->insertGetId([
            'name' => 'Pak Kobesah (Owner)',
            'email' => 'owner@kobesah.id',
            'password' => bcrypt('password'),
            'is_superadmin' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kasirId = DB::table('users')->insertGetId([
            'name' => 'Dewi (Kasir)',
            'email' => 'kasir@kobesah.id',
            'password' => bcrypt('password'),
            'is_superadmin' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kokiId = DB::table('users')->insertGetId([
            'name' => 'Ahmad (Koki)',
            'email' => 'koki@kobesah.id',
            'password' => bcrypt('password'),
            'is_superadmin' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Seed Organization
        $orgId = DB::table('organizations')->insertGetId([
            'name' => 'kobesah-godean',
            'slug' => 'kobesah-godean',
            'is_active' => true,
            'phone' => '0274-987654',
            'email' => 'contact@kobesah.id',
            'address' => 'Jl. Godean KM 5',
            'city' => 'Sleman',
            'province' => 'D.I. Yogyakarta',
            'postal_code' => '55293',
            'latitude' => -7.779352,
            'longitude' => 110.334567,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'tax_enabled' => true,
            'tax_rate' => 10.00,
            'service_charge_enabled' => true,
            'service_charge_rate' => 5.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Seed Members
        DB::table('organization_members')->insert([
            [
                'organization_id' => $orgId,
                'user_id' => $ownerId,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $orgId,
                'user_id' => $kasirId,
                'role' => 'cashier',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $orgId,
                'user_id' => $kokiId,
                'role' => 'kitchen',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 4. Seed Dining Tables
        $table1Id = DB::table('dining_tables')->insertGetId([
            'organization_id' => $orgId,
            'name' => 'Meja 01',
            'code' => 'K01',
            'capacity' => 2,
            'location' => 'Area Depan',
            'qr_token' => 'qr_token_meja_01_kobesah_godean',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $table2Id = DB::table('dining_tables')->insertGetId([
            'organization_id' => $orgId,
            'name' => 'Meja 02',
            'code' => 'K02',
            'capacity' => 4,
            'location' => 'Area Depan',
            'qr_token' => 'qr_token_meja_02_kobesah_godean',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $table3Id = DB::table('dining_tables')->insertGetId([
            'organization_id' => $orgId,
            'name' => 'Meja 03',
            'code' => 'K03',
            'capacity' => 4,
            'location' => 'Area Belakang',
            'qr_token' => 'qr_token_meja_03_kobesah_godean',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $table4Id = DB::table('dining_tables')->insertGetId([
            'organization_id' => $orgId,
            'name' => 'Meja 04',
            'code' => 'K04',
            'capacity' => 6,
            'location' => 'Area Belakang',
            'qr_token' => 'qr_token_meja_04_kobesah_godean',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Seed Menus
        // === DRINKS: Kopi Robusta ===
        $kopiHitamId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Kopi Hitam',
            'price' => 6000.00,
            'is_available' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kopi Hitam Ukuran Variant Group
        $hitamUkuranId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $kopiHitamId,
            'type' => 'variant_group',
            'name' => 'Ukuran',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            [
                'organization_id' => $orgId,
                'parent_id' => $hitamUkuranId,
                'type' => 'variant',
                'name' => 'Small',
                'price' => 0.00,
                'is_available' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $orgId,
                'parent_id' => $hitamUkuranId,
                'type' => 'variant',
                'name' => 'Large',
                'price' => 1000.00,
                'is_available' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $kopiSusuId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Kopi Susu',
            'price' => 7000.00,
            'is_available' => true,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kopi Susu Ukuran Variant Group
        $susuUkuranId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $kopiSusuId,
            'type' => 'variant_group',
            'name' => 'Ukuran',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            [
                'organization_id' => $orgId,
                'parent_id' => $susuUkuranId,
                'type' => 'variant',
                'name' => 'Small',
                'price' => 0.00,
                'is_available' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $orgId,
                'parent_id' => $susuUkuranId,
                'type' => 'variant',
                'name' => 'Large',
                'price' => 2000.00,
                'is_available' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $kopajaId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Kopaja (Kopi hitam + jahe)',
            'price' => 8000.00,
            'is_available' => true,
            'sort_order' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kopiSueId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Kopi SUE\' (Kopi susu + jahe)',
            'price' => 10000.00,
            'is_available' => true,
            'sort_order' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // General Addon Group: Klotok
        $klotokAddonGroupId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'addon_group',
            'name' => 'Tambahan Kopi',
            'is_available' => true,
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $klotokAddonId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $klotokAddonGroupId,
            'type' => 'addon',
            'name' => 'PLUS Klotok',
            'price' => 1000.00,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // === DRINKS: Ice Coffee (ICE) ===
        $iceCoffeeHitamId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Ice Coffee Hitam',
            'price' => 10000.00,
            'is_available' => true,
            'sort_order' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $iceCoffeeKobeId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Ice Coffee Kobe (Ice coffee + susu)',
            'price' => 10000.00,
            'is_available' => true,
            'sort_order' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $iceCoffeeArenId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Ice Coffee Aren',
            'price' => 13000.00,
            'is_available' => true,
            'sort_order' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $iceCoffeeAlmondId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Ice Coffee Almond',
            'price' => 14000.00,
            'is_available' => true,
            'sort_order' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $iceCoffeeCaramelId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Ice Coffee Caramel',
            'price' => 14000.00,
            'is_available' => true,
            'sort_order' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $iceCoffeeHazelnutId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Ice Coffee Hazelnut',
            'price' => 14000.00,
            'is_available' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $iceCoffeePandanId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Ice Coffee Pandan',
            'price' => 14000.00,
            'is_available' => true,
            'sort_order' => 11,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $iceCoffeeVanillaId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Ice Coffee Vanilla',
            'price' => 14000.00,
            'is_available' => true,
            'sort_order' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ice Coffee Addon Group: Cincau
        $cincauAddonGroupId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'addon_group',
            'name' => 'Toping Es Kopi',
            'is_available' => true,
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cincauAddonId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $cincauAddonGroupId,
            'type' => 'addon',
            'name' => 'Cincau',
            'price' => 2000.00,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // === DRINKS: Non Coffee (ICE) ===
        $matchaId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Matcha',
            'price' => 15000.00,
            'is_available' => true,
            'sort_order' => 13,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $redVelvetId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Red Velvet',
            'price' => 15000.00,
            'is_available' => true,
            'sort_order' => 14,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $taroId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Taro',
            'price' => 15000.00,
            'is_available' => true,
            'sort_order' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // === DRINKS: Cokelat ===
        $cokelatOriginalId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Cokelat Original',
            'price' => 12000.00,
            'is_available' => true,
            'sort_order' => 16,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cokelatSuhuId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $cokelatOriginalId,
            'type' => 'variant_group',
            'name' => 'Penyajian',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            [
                'organization_id' => $orgId,
                'parent_id' => $cokelatSuhuId,
                'type' => 'variant',
                'name' => 'HOT',
                'price' => 0.00,
                'is_available' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $orgId,
                'parent_id' => $cokelatSuhuId,
                'type' => 'variant',
                'name' => 'ICE',
                'price' => 2000.00,
                'is_available' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Cokelat Variant Groups helper for other chocolates (Almond, Caramel, Vanilla, Pandan base 13K, ICE +2000)
        $chocolateNames = ['Cokelat Almond', 'Cokelat Caramel', 'Cokelat Vanilla', 'Cokelat Pandan'];
        $sort = 17;
        foreach ($chocolateNames as $chName) {
            $chId = DB::table('menus')->insertGetId([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $chName,
                'price' => 13000.00,
                'is_available' => true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $chSuhuId = DB::table('menus')->insertGetId([
                'organization_id' => $orgId,
                'parent_id' => $chId,
                'type' => 'variant_group',
                'name' => 'Penyajian',
                'is_available' => true,
                'is_required' => true,
                'min_select' => 1,
                'max_select' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('menus')->insert([
                [
                    'organization_id' => $orgId,
                    'parent_id' => $chSuhuId,
                    'type' => 'variant',
                    'name' => 'HOT',
                    'price' => 0.00,
                    'is_available' => true,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'organization_id' => $orgId,
                    'parent_id' => $chSuhuId,
                    'type' => 'variant',
                    'name' => 'ICE',
                    'price' => 2000.00,
                    'is_available' => true,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        // === DRINKS: Jeruk ===
        $jerukLemonId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Jeruk Lemon',
            'price' => 7000.00,
            'is_available' => true,
            'sort_order' => 21,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lemonSuhuId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $jerukLemonId,
            'type' => 'variant_group',
            'name' => 'Penyajian',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            [
                'organization_id' => $orgId,
                'parent_id' => $lemonSuhuId,
                'type' => 'variant',
                'name' => 'HOT',
                'price' => 0.00,
                'is_available' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $orgId,
                'parent_id' => $lemonSuhuId,
                'type' => 'variant',
                'name' => 'ICE',
                'price' => 1000.00,
                'is_available' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $jerukNipisId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Jeruk Nipis',
            'price' => 7000.00,
            'is_available' => true,
            'sort_order' => 22,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nipisSuhuId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $jerukNipisId,
            'type' => 'variant_group',
            'name' => 'Penyajian',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            [
                'organization_id' => $orgId,
                'parent_id' => $nipisSuhuId,
                'type' => 'variant',
                'name' => 'HOT',
                'price' => 0.00,
                'is_available' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $orgId,
                'parent_id' => $nipisSuhuId,
                'type' => 'variant',
                'name' => 'ICE',
                'price' => 1000.00,
                'is_available' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // === DRINKS: MILK SERIES ===
        $milkteaId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Milktea',
            'price' => 10000.00,
            'is_available' => true,
            'sort_order' => 23,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $milkteaSuhuId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $milkteaId,
            'type' => 'variant_group',
            'name' => 'Penyajian',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            ['organization_id' => $orgId, 'parent_id' => $milkteaSuhuId, 'type' => 'variant', 'name' => 'HOT', 'price' => 0.00, 'is_available' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $orgId, 'parent_id' => $milkteaSuhuId, 'type' => 'variant', 'name' => 'ICE', 'price' => 0.00, 'is_available' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()]
        ]);

        $milkTapeKetanId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Milk Tape Ketan',
            'price' => 10000.00,
            'is_available' => true,
            'sort_order' => 24,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tapeSuhuId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $milkTapeKetanId,
            'type' => 'variant_group',
            'name' => 'Penyajian',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            ['organization_id' => $orgId, 'parent_id' => $tapeSuhuId, 'type' => 'variant', 'name' => 'HOT', 'price' => 0.00, 'is_available' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $orgId, 'parent_id' => $tapeSuhuId, 'type' => 'variant', 'name' => 'ICE', 'price' => 1000.00, 'is_available' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()]
        ]);

        $milkStrawberryId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Milk Strawberry (ICE)',
            'price' => 13000.00,
            'is_available' => true,
            'sort_order' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $milkKleponId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Milk Klepon (ICE)',
            'price' => 15000.00,
            'is_available' => true,
            'sort_order' => 26,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $milkDawetId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Milk Dawet (ICE)',
            'price' => 15000.00,
            'is_available' => true,
            'sort_order' => 27,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // === DRINKS: SQUASH (ICE) ===
        $squashItems = [
            'Lychee Squash' => 13000.00,
            'Lemon Squash' => 12000.00,
            'Mojito Mint Squash' => 12000.00,
            'Strawberry Squash' => 12000.00,
            'Green Apple Squash' => 12000.00
        ];
        $sort = 28;
        foreach ($squashItems as $sqName => $sqPrice) {
            DB::table('menus')->insert([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $sqName,
                'price' => $sqPrice,
                'is_available' => true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === DRINKS: JUICE (ICE) ===
        $juiceItems = [
            'Jambu Juice' => 10000.00,
            'Alpukat Juice' => 13000.00,
            'Mangga Juice' => 13000.00,
            'Strawberry Juice' => 12000.00
        ];
        foreach ($juiceItems as $jcName => $jcPrice) {
            DB::table('menus')->insert([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $jcName,
                'price' => $jcPrice,
                'is_available' => true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === DRINKS: BLEND (ICE) ===
        $blendItems = [
            'Vanilla Blend' => 13000.00,
            'Cappucinno Blend' => 13000.00,
            'Strawberry Blend' => 13000.00
        ];
        foreach ($blendItems as $blName => $blPrice) {
            DB::table('menus')->insert([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $blName,
                'price' => $blPrice,
                'is_available' => true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === DRINKS: TEA SERIES ===
        $originalTeaId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Original Tea',
            'price' => 5000.00,
            'is_available' => true,
            'sort_order' => $sort++,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teaSuhuId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $originalTeaId,
            'type' => 'variant_group',
            'name' => 'Penyajian',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            ['organization_id' => $orgId, 'parent_id' => $teaSuhuId, 'type' => 'variant', 'name' => 'HOT', 'price' => 0.00, 'is_available' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $orgId, 'parent_id' => $teaSuhuId, 'type' => 'variant', 'name' => 'ICE', 'price' => 0.00, 'is_available' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()]
        ]);

        $lemonTeaId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Lemon Tea',
            'price' => 7000.00,
            'is_available' => true,
            'sort_order' => $sort++,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lemonTeaSuhuId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $lemonTeaId,
            'type' => 'variant_group',
            'name' => 'Penyajian',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            ['organization_id' => $orgId, 'parent_id' => $lemonTeaSuhuId, 'type' => 'variant', 'name' => 'HOT', 'price' => 0.00, 'is_available' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $orgId, 'parent_id' => $lemonTeaSuhuId, 'type' => 'variant', 'name' => 'ICE', 'price' => 1000.00, 'is_available' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()]
        ]);

        $tehHijauId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Teh Hijau',
            'price' => 7000.00,
            'is_available' => true,
            'sort_order' => $sort++,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tehHijauSuhuId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $tehHijauId,
            'type' => 'variant_group',
            'name' => 'Penyajian',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            ['organization_id' => $orgId, 'parent_id' => $tehHijauSuhuId, 'type' => 'variant', 'name' => 'HOT', 'price' => 0.00, 'is_available' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $orgId, 'parent_id' => $tehHijauSuhuId, 'type' => 'variant', 'name' => 'ICE', 'price' => 1000.00, 'is_available' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()]
        ]);

        DB::table('menus')->insert([
            [
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => 'Lychee Tea (ICE)',
                'price' => 10000.00,
                'is_available' => true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => 'Lychee Tea Mint (ICE)',
                'price' => 14000.00,
                'is_available' => true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // === DRINKS: WEDANGAN (HOT) ===
        $wedanganItems = ['Teh Jahe', 'Jahe Serai', 'Lemon Jahe', 'Susu Jahe'];
        foreach ($wedanganItems as $wdName) {
            DB::table('menus')->insert([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $wdName,
                'price' => 10000.00,
                'is_available' => true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === FOOD: Maindish ===
        // Maindish (Pilihan Sambal: Bawang, Mateng)
        $sambalGroupMaindishId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'addon_group',
            'name' => 'Pilihan Sambal',
            'is_available' => true,
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            ['organization_id' => $orgId, 'parent_id' => $sambalGroupMaindishId, 'type' => 'addon', 'name' => 'Sambal Bawang', 'price' => 0.00, 'is_available' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $orgId, 'parent_id' => $sambalGroupMaindishId, 'type' => 'addon', 'name' => 'Sambal Mateng', 'price' => 0.00, 'is_available' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()]
        ]);

        $maindishes = [
            'Nasi Telur' => 10000.00,
            'Nasi Telur + Tempe' => 12000.00,
            'Nasi Telur + Nugget' => 12000.00,
            'Nasi Telur + Jamur' => 12000.00,
            'Nasi Ayam' => 16000.00,
            'Nasi Ayam + Nugget' => 18000.00,
            'Nasi Ayam + Jamur' => 18000.00
        ];
        $foodSort = 100;
        $nasiTelurTempeId = null;
        foreach ($maindishes as $mdName => $mdPrice) {
            $insertedId = DB::table('menus')->insertGetId([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $mdName,
                'price' => $mdPrice,
                'is_available' => true,
                'sort_order' => $foodSort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($mdName === 'Nasi Telur + Tempe') {
                $nasiTelurTempeId = $insertedId;
            }
        }

        // === FOOD: Nasgor ===
        $nasgors = [
            'Nasi Goreng Kobe' => 12000.00,
            'Nasi Goreng Oriental' => 15000.00,
            'Nasi Goreng Jawa' => 15000.00,
            'Nasi Goreng Rempah Arab' => 15000.00,
            'Magelangan' => 15000.00
        ];
        foreach ($nasgors as $ngName => $ngPrice) {
            DB::table('menus')->insert([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $ngName,
                'price' => $ngPrice,
                'is_available' => true,
                'sort_order' => $foodSort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === FOOD: Rice Bowl ===
        $riceBowls = [
            'Ricebowl Ayam Blackpepper' => 12000.00,
            'Ricebowl Ayam Teriyaki' => 12000.00,
            'Ricebowl Ayam Rendang' => 12000.00,
            'Ricebowl Ayam Kari' => 12000.00,
            'Ricebowl Katsu Blackpepper' => 17000.00,
            'Ricebowl Katsu Teriyaki' => 17000.00,
            'Ricebowl Katsu Rendang' => 17000.00,
            'Ricebowl Katsu Kari' => 17000.00
        ];
        $katsuTeriyakiId = null;
        foreach ($riceBowls as $rbName => $rbPrice) {
            $insertedId = DB::table('menus')->insertGetId([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $rbName,
                'price' => $rbPrice,
                'is_available' => true,
                'sort_order' => $foodSort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($rbName === 'Ricebowl Katsu Teriyaki') {
                $katsuTeriyakiId = $insertedId;
            }
        }

        // === FOOD: Nasi Pecel ===
        $pecels = [
            'Nasi Pecel Ayam' => 17000.00,
            'Nasi Pecel Kobe' => 12000.00
        ];
        foreach ($pecels as $pcName => $pcPrice) {
            DB::table('menus')->insert([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $pcName,
                'price' => $pcPrice,
                'is_available' => true,
                'sort_order' => $foodSort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === FOOD: MIE ===
        $mies = [
            'Indomie Goreng Telur' => 10000.00,
            'Indomie Rebus Telur' => 10000.00,
            'Indomie Goreng Telur + Sosis' => 12000.00,
            'Indomie Rebus Telur + Sosis' => 12000.00,
            'Indomie Goreng Katsu' => 15000.00,
            'Indomie Goreng Spesial' => 15000.00,
            'Indomie Rebus Spesial' => 15000.00
        ];
        foreach ($mies as $miName => $miPrice) {
            DB::table('menus')->insert([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $miName,
                'price' => $miPrice,
                'is_available' => true,
                'sort_order' => $foodSort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === FOOD: SNACK ===
        $snacks = [
            'Mendoan' => 10000.00,
            'Singkong Goreng' => 10000.00,
            'Tahu Walik' => 10000.00,
            'Roti Bakar' => 10000.00,
            'French Fries' => 11000.00,
            'Otak-Otak' => 11000.00,
            'Nugget' => 12000.00,
            'Cireng' => 12000.00,
            'Jamur Crispy' => 12000.00,
            'Bola Udang' => 12000.00,
            'Bola Salmon' => 12000.00,
            'Mix Modern (Kentang, Bola Udang, Bola Salmon, Nugget, Otak-Otak)' => 17000.00,
            'Mix Tradisional (Mendoan, Pisang, Jamur, Singkong)' => 17000.00
        ];
        foreach ($snacks as $snName => $snPrice) {
            DB::table('menus')->insert([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $snName,
                'price' => $snPrice,
                'is_available' => true,
                'sort_order' => $foodSort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === FOOD: PISANG GORENG ===
        $pisangs = [
            'Pisang Original' => 10000.00,
            'Pisang Keju' => 12000.00,
            'Pisang Cokelat' => 12000.00,
            'Pisang Cokelat Keju' => 15000.00
        ];
        foreach ($pisangs as $psName => $psPrice) {
            DB::table('menus')->insert([
                'organization_id' => $orgId,
                'parent_id' => null,
                'type' => 'product',
                'name' => $psName,
                'price' => $psPrice,
                'is_available' => true,
                'sort_order' => $foodSort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 6. Seed Orders (Selesai Cash)
        // ORD-KOBESAH-0001: Nasi Telur + Tempe (qty 2) & Ice Coffee Kobe (qty 2)
        // Subtotal = (12000 * 2) + (10000 * 2) = 44000
        // Tax (10%) = 4400, Service (5%) = 2200, Total = 50600, Bayar = 100000, Kembali = 49400
        $order1Id = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-KOBESAH-0001',
            'organization_id' => $orgId,
            'dining_table_id' => $table1Id,
            'created_by' => $kasirId,
            'customer_name' => 'Agus',
            'order_type' => 'table_order',
            'bill_status' => 'none',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'payment_amount' => 100000.00,
            'subtotal_amount' => 44000.00,
            'discount_amount' => 0.00,
            'tax_rate_snapshot' => 10.00,
            'tax_amount' => 4400.00,
            'service_charge_rate_snapshot' => 5.00,
            'service_charge_amount' => 2200.00,
            'total_amount' => 50600.00,
            'change_amount' => 49400.00,
            'paid_at' => now(),
            'opened_at' => now(),
            'closed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order1Item1Id = DB::table('order_items')->insertGetId([
            'order_id' => $order1Id,
            'menu_id' => $nasiTelurTempeId,
            'parent_item_id' => null,
            'item_type' => 'product',
            'name' => 'Nasi Telur + Tempe',
            'price' => 12000.00,
            'base_price' => 12000.00,
            'variant_total' => 0.00,
            'unit_price' => 12000.00,
            'quantity' => 2,
            'subtotal' => 24000.00,
            'item_status' => 'served',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sambal Bawang Addon
        DB::table('order_items')->insert([
            'order_id' => $order1Id,
            'menu_id' => DB::table('menus')->where('parent_id', $sambalGroupMaindishId)->where('name', 'Sambal Bawang')->value('id'),
            'parent_item_id' => $order1Item1Id,
            'item_type' => 'addon',
            'name' => 'Sambal Bawang',
            'price' => 0.00,
            'base_price' => 0.00,
            'variant_total' => 0.00,
            'unit_price' => 0.00,
            'quantity' => 2,
            'subtotal' => 0.00,
            'item_status' => 'served',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $order1Id,
            'menu_id' => $iceCoffeeKobeId,
            'parent_item_id' => null,
            'item_type' => 'product',
            'name' => 'Ice Coffee Kobe (Ice coffee + susu)',
            'price' => 10000.00,
            'base_price' => 10000.00,
            'variant_total' => 0.00,
            'unit_price' => 10000.00,
            'quantity' => 2,
            'subtotal' => 20000.00,
            'item_status' => 'served',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ORD-KOBESAH-0002: Ricebowl Katsu Teriyaki (qty 1) & Milk Klepon (qty 1)
        // Subtotal = 17000 + 15000 = 32000
        // Tax = 3200, Service = 1600, Total = 36800, Bayar = 50000, Kembali = 13200
        $order2Id = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-KOBESAH-0002',
            'organization_id' => $orgId,
            'dining_table_id' => $table2Id,
            'created_by' => $kasirId,
            'customer_name' => 'Fitri',
            'order_type' => 'cashier_order',
            'bill_status' => 'none',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'payment_amount' => 50000.00,
            'subtotal_amount' => 32000.00,
            'discount_amount' => 0.00,
            'tax_rate_snapshot' => 10.00,
            'tax_amount' => 3200.00,
            'service_charge_rate_snapshot' => 5.00,
            'service_charge_amount' => 1600.00,
            'total_amount' => 36800.00,
            'change_amount' => 13200.00,
            'paid_at' => now(),
            'opened_at' => now(),
            'closed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => $order2Id,
                'menu_id' => $katsuTeriyakiId,
                'parent_item_id' => null,
                'item_type' => 'product',
                'name' => 'Ricebowl Katsu Teriyaki',
                'price' => 17000.00,
                'base_price' => 17000.00,
                'variant_total' => 0.00,
                'unit_price' => 17000.00,
                'quantity' => 1,
                'subtotal' => 17000.00,
                'item_status' => 'served',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order2Id,
                'menu_id' => $milkKleponId,
                'parent_item_id' => null,
                'item_type' => 'product',
                'name' => 'Milk Klepon (ICE)',
                'price' => 15000.00,
                'base_price' => 15000.00,
                'variant_total' => 0.00,
                'unit_price' => 15000.00,
                'quantity' => 1,
                'subtotal' => 15000.00,
                'item_status' => 'served',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $org = DB::table('organizations')->where('slug', 'kobesah-godean')->first();

        if ($org) {
            DB::table('orders')->where('organization_id', $org->id)->delete();
            DB::table('menus')->where('organization_id', $org->id)->delete();
            DB::table('dining_tables')->where('organization_id', $org->id)->delete();
            DB::table('organization_members')->where('organization_id', $org->id)->delete();
            DB::table('organizations')->where('id', $org->id)->delete();
        }

        DB::table('users')->whereIn('email', [
            'owner@kobesah.id',
            'kasir@kobesah.id',
            'koki@kobesah.id'
        ])->delete();
    }
};
