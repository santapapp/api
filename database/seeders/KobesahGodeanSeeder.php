<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillStatus;
use App\Enums\ItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KobesahGodeanSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🍖 Seeding Kobesah Godean...');

        // ================================================================
        // 1. USERS
        // ================================================================
        $owner = User::firstOrCreate(
            ['email' => 'owner@kobesah-godean.id'],
            [
                'name'     => 'Pak Slamet',
                'password' => bcrypt('password'),
            ]
        );

        $cashier1 = User::firstOrCreate(
            ['email' => 'kasir1@kobesah-godean.id'],
            [
                'name'     => 'Dewi Kasir',
                'password' => bcrypt('password'),
            ]
        );

        $cashier2 = User::firstOrCreate(
            ['email' => 'kasir2@kobesah-godean.id'],
            [
                'name'     => 'Rini Kasir',
                'password' => bcrypt('password'),
            ]
        );

        $kitchen1 = User::firstOrCreate(
            ['email' => 'dapur1@kobesah-godean.id'],
            [
                'name'     => 'Mas Joko Dapur',
                'password' => bcrypt('password'),
            ]
        );

        $kitchen2 = User::firstOrCreate(
            ['email' => 'dapur2@kobesah-godean.id'],
            [
                'name'     => 'Bu Wati Dapur',
                'password' => bcrypt('password'),
            ]
        );

        // ================================================================
        // 2. ORGANIZATION
        // ================================================================
        $org = Organization::firstOrCreate(
            ['slug' => 'kobesah-godean'],
            ['name' => 'Kobesah Godean']
        );

        // ================================================================
        // 3. MEMBERS
        // ================================================================
        $members = [
            [$owner->id,    'owner'],
            [$cashier1->id, 'cashier'],
            [$cashier2->id, 'cashier'],
            [$kitchen1->id, 'kitchen'],
            [$kitchen2->id, 'kitchen'],
        ];

        foreach ($members as [$userId, $role]) {
            OrganizationMember::firstOrCreate(
                ['organization_id' => $org->id, 'user_id' => $userId],
                ['role' => $role]
            );
        }

        // ================================================================
        // 4. DINING TABLES
        // ================================================================
        $tableNames = [
            'Meja 1', 'Meja 2', 'Meja 3', 'Meja 4', 'Meja 5',
            'Meja 6', 'Meja 7', 'Meja 8',
            'Lesehan A', 'Lesehan B', 'Lesehan C',
            'VIP 1', 'VIP 2',
            'Teras 1', 'Teras 2',
        ];

        $tables = [];
        foreach ($tableNames as $name) {
            $tables[$name] = DiningTable::firstOrCreate(
                ['organization_id' => $org->id, 'name' => $name],
                ['qr_token' => Str::random(32)]
            );
        }

        // ================================================================
        // 5. MENU TREE
        //    Kobesah: spesialis kobesan (kobe beef style), sate, bakar-bakaran
        // ================================================================

        // ── KOBESAN (signature) ──────────────────────────────────────────
        $kobesanGrp = $this->product($org->id, 'Kobesan Spesial',  75000, 1);
        $g = $this->variantGroup($org->id, $kobesanGrp->id, 'Pilihan Daging', 1);
        $this->variant($org->id, $g->id, 'Wagyu Grade A',    0, 1);
        $this->variant($org->id, $g->id, 'Wagyu Grade B',    0, 2);
        $this->variant($org->id, $g->id, 'Sapi Lokal',   -10000, 3);
        $g2 = $this->variantGroup($org->id, $kobesanGrp->id, 'Tingkat Kematangan', 2);
        $this->variant($org->id, $g2->id, 'Well Done',   0, 1);
        $this->variant($org->id, $g2->id, 'Medium Well', 0, 2);
        $this->variant($org->id, $g2->id, 'Medium',      0, 3);
        $this->variant($org->id, $g2->id, 'Medium Rare', 0, 4);
        $add1 = $this->addonGroup($org->id, $kobesanGrp->id, 'Tambahan', 3);
        $this->addon($org->id, $add1->id, 'Nasi Putih',      5000, 1);
        $this->addon($org->id, $add1->id, 'Nasi Merah',      7000, 2);
        $this->addon($org->id, $add1->id, 'Kentang Goreng',  8000, 3);
        $this->addon($org->id, $add1->id, 'Salad Sayur',     5000, 4);
        $this->addon($org->id, $add1->id, 'Saus Jamur',      5000, 5);
        $this->addon($org->id, $add1->id, 'Saus BBQ',        5000, 6);

        // ── SATE ─────────────────────────────────────────────────────────
        $sateAyam = $this->product($org->id, 'Sate Ayam',       28000, 2);
        $gs = $this->variantGroup($org->id, $sateAyam->id, 'Ukuran Porsi', 1);
        $this->variant($org->id, $gs->id, '10 Tusuk',  0,     1);
        $this->variant($org->id, $gs->id, '20 Tusuk', 22000,  2);
        $gsa = $this->addonGroup($org->id, $sateAyam->id, 'Tambahan', 2);
        $this->addon($org->id, $gsa->id, 'Lontong',    5000, 1);
        $this->addon($org->id, $gsa->id, 'Nasi Putih', 5000, 2);
        $this->addon($org->id, $gsa->id, 'Kecap Extra',2000, 3);

        $sateSapi = $this->product($org->id, 'Sate Sapi',       35000, 3);
        $gss = $this->variantGroup($org->id, $sateSapi->id, 'Ukuran Porsi', 1);
        $this->variant($org->id, $gss->id, '10 Tusuk',  0,    1);
        $this->variant($org->id, $gss->id, '20 Tusuk', 28000, 2);
        $gssa = $this->addonGroup($org->id, $sateSapi->id, 'Tambahan', 2);
        $this->addon($org->id, $gssa->id, 'Lontong',    5000, 1);
        $this->addon($org->id, $gssa->id, 'Nasi Putih', 5000, 2);

        $sateKambing = $this->product($org->id, 'Sate Kambing', 40000, 4);
        $gsk = $this->variantGroup($org->id, $sateKambing->id, 'Ukuran Porsi', 1);
        $this->variant($org->id, $gsk->id, '10 Tusuk',  0,    1);
        $this->variant($org->id, $gsk->id, '20 Tusuk', 32000, 2);

        // ── BAKAR-BAKARAN ─────────────────────────────────────────────────
        $ikanBakar = $this->product($org->id, 'Ikan Bakar',     38000, 5);
        $gib = $this->variantGroup($org->id, $ikanBakar->id, 'Pilihan Ikan', 1);
        $this->variant($org->id, $gib->id, 'Gurame',       0,     1);
        $this->variant($org->id, $gib->id, 'Nila',        -8000,  2);
        $this->variant($org->id, $gib->id, 'Bawal',        5000,  3);
        $this->variant($org->id, $gib->id, 'Kakap Merah', 15000,  4);
        $gibp = $this->variantGroup($org->id, $ikanBakar->id, 'Bumbu', 2);
        $this->variant($org->id, $gibp->id, 'Bumbu Bali',   0,    1);
        $this->variant($org->id, $gibp->id, 'Bumbu Padang', 0,    2);
        $this->variant($org->id, $gibp->id, 'Polos',        0,    3);
        $aib = $this->addonGroup($org->id, $ikanBakar->id, 'Tambahan', 3);
        $this->addon($org->id, $aib->id, 'Nasi Putih',   5000, 1);
        $this->addon($org->id, $aib->id, 'Lalapan',      3000, 2);
        $this->addon($org->id, $aib->id, 'Sambal Terasi',2000, 3);
        $this->addon($org->id, $aib->id, 'Sambal Matah', 3000, 4);

        $ayamBarek = $this->product($org->id, 'Ayam Bakar Barek', 32000, 6);
        $gab = $this->variantGroup($org->id, $ayamBarek->id, 'Bagian Ayam', 1);
        $this->variant($org->id, $gab->id, 'Paha Bawah',  0,    1);
        $this->variant($org->id, $gab->id, 'Paha Atas',   0,    2);
        $this->variant($org->id, $gab->id, 'Dada',       -3000, 3);
        $this->variant($org->id, $gab->id, 'Sayap',      -5000, 4);
        $gabl = $this->addonGroup($org->id, $ayamBarek->id, 'Lalapan & Sambal', 2);
        $this->addon($org->id, $gabl->id, 'Lalapan Lengkap',  5000, 1);
        $this->addon($org->id, $gabl->id, 'Sambal Terasi',    2000, 2);
        $this->addon($org->id, $gabl->id, 'Sambal Ijo',       2000, 3);
        $this->addon($org->id, $gabl->id, 'Nasi Putih',       5000, 4);

        // ── NASI & MIE ────────────────────────────────────────────────────
        $nasiGoreng = $this->product($org->id, 'Nasi Goreng Kobesah', 25000, 7);
        $gng = $this->variantGroup($org->id, $nasiGoreng->id, 'Level Pedas', 1);
        $this->variant($org->id, $gng->id, 'Tidak Pedas', 0, 1);
        $this->variant($org->id, $gng->id, 'Sedang',      0, 2);
        $this->variant($org->id, $gng->id, 'Pedas',       0, 3);
        $this->variant($org->id, $gng->id, 'Extra Pedas', 0, 4);
        $ang = $this->addonGroup($org->id, $nasiGoreng->id, 'Tambahan', 2);
        $this->addon($org->id, $ang->id, 'Telur Ceplok',  3000, 1);
        $this->addon($org->id, $ang->id, 'Telur Dadar',   3000, 2);
        $this->addon($org->id, $ang->id, 'Sosis',         5000, 3);
        $this->addon($org->id, $ang->id, 'Ayam Suwir',    8000, 4);
        $this->addon($org->id, $ang->id, 'Kerupuk',       2000, 5);

        $nasiUduk = $this->product($org->id, 'Nasi Uduk Komplit', 27000, 8);
        $gnu = $this->addonGroup($org->id, $nasiUduk->id, 'Lauk', 1);
        $this->addon($org->id, $gnu->id, 'Ayam Goreng',   8000, 1);
        $this->addon($org->id, $gnu->id, 'Empal Gepuk',  10000, 2);
        $this->addon($org->id, $gnu->id, 'Tahu Goreng',   3000, 3);
        $this->addon($org->id, $gnu->id, 'Tempe Goreng',  3000, 4);

        $mieGoreng = $this->product($org->id, 'Mie Goreng Spesial', 22000, 9);
        $gmg = $this->variantGroup($org->id, $mieGoreng->id, 'Level Pedas', 1);
        $this->variant($org->id, $gmg->id, 'Tidak Pedas', 0, 1);
        $this->variant($org->id, $gmg->id, 'Pedas',       0, 2);
        $this->variant($org->id, $gmg->id, 'Extra Pedas', 0, 3);

        // ── SOUP & BERKUAH ────────────────────────────────────────────────
        $sotoKampung = $this->product($org->id, 'Soto Kampung', 22000, 10);
        $gsk2 = $this->variantGroup($org->id, $sotoKampung->id, 'Pilihan Isi', 1);
        $this->variant($org->id, $gsk2->id, 'Ayam',    0,    1);
        $this->variant($org->id, $gsk2->id, 'Sapi',   5000,  2);
        $this->variant($org->id, $gsk2->id, 'Campur',  2000, 3);
        $ask = $this->addonGroup($org->id, $sotoKampung->id, 'Tambahan', 2);
        $this->addon($org->id, $ask->id, 'Nasi Putih',  5000, 1);
        $this->addon($org->id, $ask->id, 'Lontong',     5000, 2);
        $this->addon($org->id, $ask->id, 'Emping',      3000, 3);
        $this->addon($org->id, $ask->id, 'Telur Rebus', 3000, 4);

        $gulaiKambing = $this->product($org->id, 'Gulai Kambing', 45000, 11);
        $agk = $this->addonGroup($org->id, $gulaiKambing->id, 'Tambahan', 1);
        $this->addon($org->id, $agk->id, 'Nasi Putih',   5000, 1);
        $this->addon($org->id, $agk->id, 'Nasi Merah',   7000, 2);
        $this->addon($org->id, $agk->id, 'Roti Jala',    8000, 3);

        $rawon = $this->product($org->id, 'Rawon Sapi', 35000, 12);
        $arw = $this->addonGroup($org->id, $rawon->id, 'Tambahan', 1);
        $this->addon($org->id, $arw->id, 'Nasi Putih',   5000, 1);
        $this->addon($org->id, $arw->id, 'Toge',         2000, 2);
        $this->addon($org->id, $arw->id, 'Telur Asin',   3000, 3);
        $this->addon($org->id, $arw->id, 'Kerupuk',      2000, 4);

        // ── GORENGAN & CEMILAN ────────────────────────────────────────────
        $this->product($org->id, 'Tahu Tempe Bacem',    15000, 13);
        $this->product($org->id, 'Perkedel Jagung',     12000, 14);
        $this->product($org->id, 'Kerupuk Udang',        8000, 15);
        $this->product($org->id, 'Emping Melinjo',       8000, 16);

        $gorenganMix = $this->product($org->id, 'Gorengan Mix', 18000, 17);
        $ggm = $this->addonGroup($org->id, $gorenganMix->id, 'Isian', 1);
        $this->addon($org->id, $ggm->id, 'Bakwan Jagung',  0, 1);
        $this->addon($org->id, $ggm->id, 'Tahu Isi',       0, 2);
        $this->addon($org->id, $ggm->id, 'Pisang Goreng',  0, 3);
        $this->addon($org->id, $ggm->id, 'Ubi Goreng',     0, 4);

        // ── MINUMAN DINGIN ────────────────────────────────────────────────
        $esTehManis = $this->product($org->id, 'Es Teh Manis',    6000, 18);
        $esTehSusu  = $this->product($org->id, 'Es Teh Susu',     9000, 19);
        $esJeruk    = $this->product($org->id, 'Es Jeruk',        8000, 20);

        $juskePilih = $this->product($org->id, 'Jus Buah',       15000, 21);
        $gjk = $this->variantGroup($org->id, $juskePilih->id, 'Pilihan Rasa', 1);
        $this->variant($org->id, $gjk->id, 'Alpukat',   0,    1);
        $this->variant($org->id, $gjk->id, 'Mangga',    0,    2);
        $this->variant($org->id, $gjk->id, 'Sirsak',    0,    3);
        $this->variant($org->id, $gjk->id, 'Terong Belanda', 0, 4);
        $this->variant($org->id, $gjk->id, 'Melon',     0,    5);
        $gjks = $this->variantGroup($org->id, $juskePilih->id, 'Kekentalan', 2);
        $this->variant($org->id, $gjks->id, 'Biasa',  0,    1);
        $this->variant($org->id, $gjks->id, 'Kental',  3000, 2);

        $esKelapa = $this->product($org->id, 'Es Kelapa Muda',   18000, 22);
        $gek = $this->variantGroup($org->id, $esKelapa->id, 'Isian', 1);
        $this->variant($org->id, $gek->id, 'Original', 0, 1);
        $this->variant($org->id, $gek->id, '+ Cincau',  3000, 2);
        $this->variant($org->id, $gek->id, '+ Nata de Coco', 3000, 3);

        $esCoklat = $this->product($org->id, 'Es Coklat Susu',   12000, 23);
        $esMilo   = $this->product($org->id, 'Es Milo',          12000, 24);

        // ── MINUMAN PANAS ──────────────────────────────────────────────────
        $kopiHitam    = $this->product($org->id, 'Kopi Hitam',   8000, 25);
        $kopiSusu     = $this->product($org->id, 'Kopi Susu',   12000, 26);
        $tehHangat    = $this->product($org->id, 'Teh Hangat',   6000, 27);
        $jaheHangat   = $this->product($org->id, 'Wedang Jahe', 10000, 28);

        $gkopi = $this->variantGroup($org->id, $kopiSusu->id, 'Tingkat Manis', 1);
        $this->variant($org->id, $gkopi->id, 'Less Sweet', 0, 1);
        $this->variant($org->id, $gkopi->id, 'Normal',     0, 2);
        $this->variant($org->id, $gkopi->id, 'Extra Sweet',0, 3);

        $this->command->info("  ✅ Menu selesai: 28 produk dibuat");

        // ================================================================
        // 6. SAMPLE ORDERS  (sudah closed / history)
        // ================================================================

        // -- Order 1: Meja 3, sudah selesai, bayar cash --
        $this->createClosedCashOrder(
            org: $org,
            table: $tables['Meja 3'],
            createdBy: $cashier1,
            items: [
                // [menu, qty, note, children_menus[]]
                [$kobesanGrp,  1, 'jangan terlalu gosong', [$sateSapi]],
                [$sotoKampung, 2, null, []],
                [$esTehManis,  2, null, []],
            ],
            paymentAmount: 160000,
            minutesAgo: 90
        );

        // -- Order 2: Lesehan A, sudah selesai, bayar QRIS --
        $this->createClosedQrisOrder(
            org: $org,
            table: $tables['Lesehan A'],
            createdBy: $cashier2,
            items: [
                [$ikanBakar,  2, 'bumbu bali pedas', []],
                [$nasiGoreng, 1, null, []],
                [$esJeruk,    2, null, []],
                [$esTehManis, 1, null, []],
            ],
            minutesAgo: 60
        );

        // -- Order 3: VIP 1, sedang berlangsung (open) --
        $order3 = $this->createOpenOrder(
            org: $org,
            table: $tables['VIP 1'],
            createdBy: $cashier1,
            items: [
                [$gulaiKambing,  1, null, []],
                [$sateAyam,      2, null, []],
                [$juskePilih,    3, null, []],
                [$kopiSusu,      2, null, []],
            ],
            minutesAgo: 25
        );

        // -- Order 4: Meja 5, open bill customer self-order --
        $this->createOpenOrder(
            org: $org,
            table: $tables['Meja 5'],
            createdBy: $cashier2,
            items: [
                [$nasiGoreng,    2, 'extra pedas', []],
                [$mieGoreng,     1, null, []],
                [$esTehManis,    3, null, []],
            ],
            minutesAgo: 15,
            orderType: OrderType::OpenBill
        );

        $this->command->info("  ✅ Sample orders selesai");
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('🎉 Kobesah Godean seed selesai!');
        $this->command->info('');
        $this->command->info('📋 AKUN LOGIN:');
        $this->command->info("  Owner  : owner@kobesah-godean.id  / password");
        $this->command->info("  Kasir1 : kasir1@kobesah-godean.id / password");
        $this->command->info("  Kasir2 : kasir2@kobesah-godean.id / password");
        $this->command->info("  Dapur1 : dapur1@kobesah-godean.id / password");
        $this->command->info("  Dapur2 : dapur2@kobesah-godean.id / password");
        $this->command->info('');
        $this->command->info("  Org slug : kobesah-godean");
        $this->command->info("  Org ID   : {$org->id}");
        $this->command->info('========================================');
    }

    // ====================================================================
    // HELPERS: Menu creation
    // ====================================================================

    private function product(int $orgId, string $name, int $price, int $sort): Menu
    {
        return Menu::firstOrCreate(
            ['organization_id' => $orgId, 'name' => $name, 'parent_id' => null],
            [
                'type'       => 'product',
                'price'      => $price,
                'sort_order' => $sort,
            ]
        );
    }

    private function variantGroup(int $orgId, int $parentId, string $name, int $sort): Menu
    {
        return Menu::firstOrCreate(
            ['organization_id' => $orgId, 'parent_id' => $parentId, 'name' => $name, 'type' => 'variant_group'],
            [
                'price'      => 0,
                'sort_order' => $sort,
            ]
        );
    }

    private function variant(int $orgId, int $parentId, string $name, int $price, int $sort): Menu
    {
        return Menu::firstOrCreate(
            ['organization_id' => $orgId, 'parent_id' => $parentId, 'name' => $name, 'type' => 'variant'],
            [
                'price'      => $price,
                'sort_order' => $sort,
            ]
        );
    }

    private function addonGroup(int $orgId, int $parentId, string $name, int $sort): Menu
    {
        return Menu::firstOrCreate(
            ['organization_id' => $orgId, 'parent_id' => $parentId, 'name' => $name, 'type' => 'addon_group'],
            [
                'price'      => 0,
                'sort_order' => $sort,
            ]
        );
    }

    private function addon(int $orgId, int $parentId, string $name, int $price, int $sort): Menu
    {
        return Menu::firstOrCreate(
            ['organization_id' => $orgId, 'parent_id' => $parentId, 'name' => $name, 'type' => 'addon'],
            [
                'price'      => $price,
                'sort_order' => $sort,
            ]
        );
    }

    // ====================================================================
    // HELPERS: Order creation
    // ====================================================================

    /**
     * Buat order sudah CLOSED, bayar cash.
     */
    private function createClosedCashOrder(
        Organization $org,
        DiningTable $table,
        User $createdBy,
        array $items,
        int $paymentAmount,
        int $minutesAgo
    ): Order {
        $openedAt  = now()->subMinutes($minutesAgo);
        $closedAt  = $openedAt->copy()->addMinutes(rand(20, 40));

        $order = Order::create([
            'order_number'    => Order::generateOrderNumber($org->id),
            'public_token'    => Str::random(32),
            'organization_id' => $org->id,
            'dining_table_id' => $table->id,
            'created_by'      => $createdBy->id,
            'order_type'      => OrderType::TableOrder,
            'bill_status'     => BillStatus::Closed,
            'order_status'    => OrderStatus::Completed,
            'payment_status'  => PaymentStatus::Paid,
            'payment_method'  => 'cash',
            'payment_amount'  => $paymentAmount,
            'opened_at'       => $openedAt,
            'paid_at'         => $closedAt,
            'closed_at'       => $closedAt,
        ]);

        $this->attachItems($order, $items, 'served');
        $order->recalculate();
        $order->update(['payment_amount' => $paymentAmount]);

        return $order;
    }

    /**
     * Buat order sudah CLOSED, bayar QRIS.
     */
    private function createClosedQrisOrder(
        Organization $org,
        DiningTable $table,
        User $createdBy,
        array $items,
        int $minutesAgo
    ): Order {
        $openedAt = now()->subMinutes($minutesAgo);
        $closedAt = $openedAt->copy()->addMinutes(rand(20, 40));

        $order = Order::create([
            'order_number'      => Order::generateOrderNumber($org->id),
            'public_token'      => Str::random(32),
            'organization_id'   => $org->id,
            'dining_table_id'   => $table->id,
            'created_by'        => $createdBy->id,
            'order_type'        => OrderType::TableOrder,
            'bill_status'       => BillStatus::Closed,
            'order_status'      => OrderStatus::Completed,
            'payment_status'    => PaymentStatus::Paid,
            'payment_method'    => 'qris',
            'payment_reference' => 'santap-qris-' . Str::random(8),
            'opened_at'         => $openedAt,
            'paid_at'           => $closedAt,
            'closed_at'         => $closedAt,
        ]);

        $this->attachItems($order, $items, 'served');
        $order->recalculate();
        $order->update(['payment_amount' => (float) $order->total_amount]);

        return $order;
    }

    /**
     * Buat order OPEN (sedang berlangsung).
     */
    private function createOpenOrder(
        Organization $org,
        DiningTable $table,
        User $createdBy,
        array $items,
        int $minutesAgo,
        OrderType $orderType = OrderType::TableOrder
    ): Order {
        $order = Order::create([
            'order_number'    => Order::generateOrderNumber($org->id),
            'public_token'    => Str::random(32),
            'organization_id' => $org->id,
            'dining_table_id' => $table->id,
            'created_by'      => $createdBy->id,
            'order_type'      => $orderType,
            'bill_status'     => BillStatus::Open,
            'order_status'    => OrderStatus::Confirmed,
            'payment_status'  => PaymentStatus::Unpaid,
            'opened_at'       => now()->subMinutes($minutesAgo),
        ]);

        $this->attachItems($order, $items, 'preparing');
        $order->recalculate();

        return $order;
    }

    /**
     * Attach items ke sebuah order.
     * Format $items: [Menu $menu, int $qty, ?string $note, Menu[] $childMenus]
     */
    private function attachItems(Order $order, array $items, string $itemStatus): void
    {
        foreach ($items as [$menu, $qty, $note, $children]) {
            /** @var Menu $menu */
            $item = OrderItem::create([
                'order_id'   => $order->id,
                'menu_id'    => $menu->id,
                'name'       => $menu->name,
                'price'      => $menu->price,
                'quantity'   => $qty,
                'item_status'=> $itemStatus,
                'note'       => $note,
            ]);

            foreach ($children as $childMenu) {
                /** @var Menu $childMenu */
                OrderItem::create([
                    'order_id'      => $order->id,
                    'menu_id'       => $childMenu->id,
                    'parent_item_id'=> $item->id,
                    'name'          => $childMenu->name,
                    'price'         => $childMenu->price,
                    'quantity'      => $qty,
                    'item_status'   => $itemStatus,
                ]);
            }
        }
    }
}
