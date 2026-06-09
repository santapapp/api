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
        $superadminId = DB::table('users')->insertGetId([
            'name' => 'Super Admin',
            'email' => 'superadmin@santap.id',
            'password' => bcrypt('password'),
            'is_superadmin' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownerId = DB::table('users')->insertGetId([
            'name' => 'Pak Budi (Owner)',
            'email' => 'owner@santap.id',
            'password' => bcrypt('password'),
            'is_superadmin' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kasirId = DB::table('users')->insertGetId([
            'name' => 'Siti (Kasir)',
            'email' => 'kasir@santap.id',
            'password' => bcrypt('password'),
            'is_superadmin' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kokiId = DB::table('users')->insertGetId([
            'name' => 'Chef Asep (Koki)',
            'email' => 'koki@santap.id',
            'password' => bcrypt('password'),
            'is_superadmin' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Seed Organization
        $orgId = DB::table('organizations')->insertGetId([
            'name' => 'Warung Sunda Kang Pipit',
            'slug' => 'warung-sunda-kang-pipit',
            'is_active' => true,
            'phone' => '022-123456',
            'email' => 'contact@wskangpipit.com',
            'address' => 'Jl. Pajajaran No. 24',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40117',
            'latitude' => -6.914744,
            'longitude' => 107.609810,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'tax_enabled' => true,
            'tax_rate' => 10.00,
            'service_charge_enabled' => true,
            'service_charge_rate' => 5.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Seed Organization Members
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
            'code' => 'M01',
            'capacity' => 2,
            'location' => 'Indoor Lantai 1',
            'qr_token' => 'qr_token_meja_01_wskangpipit_32ch',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $table2Id = DB::table('dining_tables')->insertGetId([
            'organization_id' => $orgId,
            'name' => 'Meja 02',
            'code' => 'M02',
            'capacity' => 4,
            'location' => 'Indoor Lantai 1',
            'qr_token' => 'qr_token_meja_02_wskangpipit_32ch',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $table3Id = DB::table('dining_tables')->insertGetId([
            'organization_id' => $orgId,
            'name' => 'Meja 03',
            'code' => 'M03',
            'capacity' => 4,
            'location' => 'Outdoor Garden',
            'qr_token' => 'qr_token_meja_03_wskangpipit_32ch',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Seed Menus
        $nasiTimbelId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Nasi Timbel Komplit',
            'price' => 35000.00,
            'is_available' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ayamGorengId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Ayam Goreng Lengkuas',
            'price' => 22000.00,
            'is_available' => true,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sayurAsemId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Sayur Asem Sunda',
            'price' => 15000.00,
            'is_available' => true,
            'sort_order' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $esTehId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Es Teh Manis',
            'price' => 6000.00,
            'is_available' => true,
            'sort_order' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $esJerukId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => null,
            'type' => 'product',
            'name' => 'Es Jeruk Peras',
            'price' => 12000.00,
            'is_available' => true,
            'sort_order' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Addon group for Sambal
        $sambalGroupId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $nasiTimbelId,
            'type' => 'addon_group',
            'name' => 'Sambal Extra',
            'price' => 0.00,
            'is_available' => true,
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 2,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sambalTerasiId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $sambalGroupId,
            'type' => 'addon',
            'name' => 'Sambal Terasi',
            'price' => 3000.00,
            'is_available' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sambalDadakId = DB::table('menus')->insertGetId([
            'organization_id' => $orgId,
            'parent_id' => $sambalGroupId,
            'type' => 'addon',
            'name' => 'Sambal Dadak',
            'price' => 3000.00,
            'is_available' => true,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6. Seed Orders (All completed paid cash)
        // Order 1: Cashier Order
        $order1Id = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-20260608-0001',
            'organization_id' => $orgId,
            'dining_table_id' => $table1Id,
            'created_by' => $kasirId,
            'customer_name' => 'Rian',
            'order_type' => 'cashier_order',
            'bill_status' => 'none',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'payment_amount' => 100000.00,
            'subtotal_amount' => 82000.00,
            'discount_amount' => 0.00,
            'tax_rate_snapshot' => 10.00,
            'tax_amount' => 8200.00,
            'service_charge_rate_snapshot' => 5.00,
            'service_charge_amount' => 4100.00,
            'total_amount' => 94300.00,
            'change_amount' => 5700.00,
            'paid_at' => now(),
            'opened_at' => now(),
            'closed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => $order1Id,
                'menu_id' => $nasiTimbelId,
                'parent_item_id' => null,
                'item_type' => 'product',
                'name' => 'Nasi Timbel Komplit',
                'price' => 35000.00,
                'base_price' => 35000.00,
                'variant_total' => 0.00,
                'unit_price' => 35000.00,
                'quantity' => 2,
                'subtotal' => 70000.00,
                'item_status' => 'served',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order1Id,
                'menu_id' => $esTehId,
                'parent_item_id' => null,
                'item_type' => 'product',
                'name' => 'Es Teh Manis',
                'price' => 6000.00,
                'base_price' => 6000.00,
                'variant_total' => 0.00,
                'unit_price' => 6000.00,
                'quantity' => 2,
                'subtotal' => 12000.00,
                'item_status' => 'served',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Order 2: Table Order
        $order2Id = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-20260608-0002',
            'organization_id' => $orgId,
            'dining_table_id' => $table2Id,
            'created_by' => $kasirId,
            'customer_name' => 'Dewi',
            'order_type' => 'table_order',
            'bill_status' => 'none',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'payment_amount' => 50000.00,
            'subtotal_amount' => 37000.00,
            'discount_amount' => 0.00,
            'tax_rate_snapshot' => 10.00,
            'tax_amount' => 3700.00,
            'service_charge_rate_snapshot' => 5.00,
            'service_charge_amount' => 1850.00,
            'total_amount' => 42550.00,
            'change_amount' => 7450.00,
            'paid_at' => now(),
            'opened_at' => now(),
            'closed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => $order2Id,
                'menu_id' => $ayamGorengId,
                'parent_item_id' => null,
                'item_type' => 'product',
                'name' => 'Ayam Goreng Lengkuas',
                'price' => 22000.00,
                'base_price' => 22000.00,
                'variant_total' => 0.00,
                'unit_price' => 22000.00,
                'quantity' => 1,
                'subtotal' => 22000.00,
                'item_status' => 'served',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order2Id,
                'menu_id' => $sayurAsemId,
                'parent_item_id' => null,
                'item_type' => 'product',
                'name' => 'Sayur Asem Sunda',
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

        // Order 3: Open Bill
        $order3Id = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-20260608-0003',
            'organization_id' => $orgId,
            'dining_table_id' => $table3Id,
            'created_by' => $kasirId,
            'customer_name' => 'Fajar',
            'order_type' => 'open_bill',
            'bill_status' => 'closed',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'payment_amount' => 150000.00,
            'subtotal_amount' => 123000.00,
            'discount_amount' => 0.00,
            'tax_rate_snapshot' => 10.00,
            'tax_amount' => 12300.00,
            'service_charge_rate_snapshot' => 5.00,
            'service_charge_amount' => 6150.00,
            'total_amount' => 141450.00,
            'change_amount' => 8550.00,
            'paid_at' => now(),
            'opened_at' => now(),
            'closed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $timbelRootId = DB::table('order_items')->insertGetId([
            'order_id' => $order3Id,
            'menu_id' => $nasiTimbelId,
            'parent_item_id' => null,
            'item_type' => 'product',
            'name' => 'Nasi Timbel Komplit',
            'price' => 35000.00,
            'base_price' => 35000.00,
            'variant_total' => 0.00,
            'unit_price' => 35000.00,
            'quantity' => 3,
            'subtotal' => 105000.00,
            'item_status' => 'served',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => $order3Id,
                'menu_id' => $sambalDadakId,
                'parent_item_id' => $timbelRootId,
                'item_type' => 'addon',
                'name' => 'Sambal Dadak',
                'price' => 3000.00,
                'base_price' => 3000.00,
                'variant_total' => 0.00,
                'unit_price' => 3000.00,
                'quantity' => 2,
                'subtotal' => 6000.00,
                'item_status' => 'served',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order3Id,
                'menu_id' => $esJerukId,
                'parent_item_id' => null,
                'item_type' => 'product',
                'name' => 'Es Jeruk Peras',
                'price' => 12000.00,
                'base_price' => 12000.00,
                'variant_total' => 0.00,
                'unit_price' => 12000.00,
                'quantity' => 1,
                'subtotal' => 12000.00,
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
        $org = DB::table('organizations')->where('slug', 'warung-sunda-kang-pipit')->first();

        if ($org) {
            DB::table('orders')->where('organization_id', $org->id)->delete();
            DB::table('menus')->where('organization_id', $org->id)->delete();
            DB::table('dining_tables')->where('organization_id', $org->id)->delete();
            DB::table('organization_members')->where('organization_id', $org->id)->delete();
            DB::table('organizations')->where('id', $org->id)->delete();
        }

        DB::table('users')->whereIn('email', [
            'superadmin@santap.id',
            'owner@santap.id',
            'kasir@santap.id',
            'koki@santap.id'
        ])->delete();
    }
};
