<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MenuStatus;
use App\Enums\OrganizationStatus;
use App\Enums\QrCodeStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Organization;
use App\Models\TableQrCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class KobesahGodeanSeeder extends Seeder
{
    /**
     * Seed data for Cafe Kobesah Godean.
     * Org slug : kobesah-godean
     * Accounts :
     *   owner   → owner.kobesah@santap.com / password
     *   cashier → cashier.kobesah@santap.com / password
     *   kitchen → kitchen.kobesah@santap.com / password
     */
    public function run(): void
    {
        // ── 1. Pastikan admin global tersedia ──────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'     => 'Santap Super Admin',
                'password' => bcrypt('password'),
                'phone'    => '081234567890',
                'status'   => 'active',
            ]
        );

        // Pastikan role administrator sudah di-assign
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        if (! $admin->hasRole('administrator')) {
            $admin->assignRole('administrator');
        }

        // ── 2. Buat user staff Kobesah ─────────────────────────────────────
        $owner = User::firstOrCreate(
            ['email' => 'owner.kobesah@santap.com'],
            [
                'name'     => 'Budi Santoso',
                'password' => bcrypt('password'),
                'phone'    => '081298765400',
                'status'   => 'active',
            ]
        );

        $cashier = User::firstOrCreate(
            ['email' => 'cashier.kobesah@santap.com'],
            [
                'name'     => 'Sari Wulandari',
                'password' => bcrypt('password'),
                'phone'    => '081298765401',
                'status'   => 'active',
            ]
        );

        $kitchen = User::firstOrCreate(
            ['email' => 'kitchen.kobesah@santap.com'],
            [
                'name'     => 'Agus Prasetyo',
                'password' => bcrypt('password'),
                'phone'    => '081298765402',
                'status'   => 'active',
            ]
        );

        // ── 3. Buat Organisasi ─────────────────────────────────────────────
        $org = Organization::firstOrCreate(
            ['slug' => 'kobesah-godean'],
            [
                'name'       => 'Kobesah Godean',
                'code'       => 'KBG',
                'email'      => 'kobesah@godean.id',
                'phone'      => '02747863210',
                'address'    => 'Jl. Godean Km 5 No. 17, Sidoarum',
                'city'       => 'Sleman',
                'province'   => 'DI Yogyakarta',
                'country'    => 'Indonesia',
                'timezone'   => 'Asia/Jakarta',
                'currency'   => 'IDR',
                'status'     => OrganizationStatus::Active,
                'created_by' => $admin->id,
            ]
        );

        // ── 4. Lampirkan staff ke organisasi ──────────────────────────────
        $this->attachIfMissing($org, $owner->id,   'owner');
        $this->attachIfMissing($org, $cashier->id, 'cashier');
        $this->attachIfMissing($org, $kitchen->id, 'kitchen');

        // ── 5. Tetapkan Spatie role di dalam konteks org ini ──────────────
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $owner->assignRole('owner');
        $cashier->assignRole('cashier');
        $kitchen->assignRole('kitchen');

        // ── 6. Kategori Menu ──────────────────────────────────────────────
        $catKopi    = $this->category($org->id, 'kopi-dan-espresso',    'Kopi & Espresso');
        $catNonKopi = $this->category($org->id, 'non-kopi',              'Non Kopi');
        $catMakanan = $this->category($org->id, 'makanan-ringan-cafe',   'Makanan Ringan');
        $catDessert = $this->category($org->id, 'dessert',               'Dessert');
        $catFusion  = $this->category($org->id, 'nasi-dan-mi',           'Nasi & Mie');

        // ── 7. Menu ───────────────────────────────────────────────────────
        // — Kopi & Espresso
        $this->menu($org->id, $catKopi->id,    'C001', 'Espresso Shot',          15000);
        $this->menu($org->id, $catKopi->id,    'C002', 'Americano',              20000);
        $this->menu($org->id, $catKopi->id,    'C003', 'Cappuccino',             25000);
        $this->menu($org->id, $catKopi->id,    'C004', 'Flat White',             27000);
        $this->menu($org->id, $catKopi->id,    'C005', 'Caramel Latte',          28000);
        $this->menu($org->id, $catKopi->id,    'C006', 'Kopi Susu Gula Aren',    22000);
        $this->menu($org->id, $catKopi->id,    'C007', 'Cold Brew',              25000);
        $this->menu($org->id, $catKopi->id,    'C008', 'Vietname Drip Ice',      23000);

        // — Non Kopi
        $this->menu($org->id, $catNonKopi->id, 'N001', 'Matcha Latte',           26000);
        $this->menu($org->id, $catNonKopi->id, 'N002', 'Taro Latte',             26000);
        $this->menu($org->id, $catNonKopi->id, 'N003', 'Chocolate Latte',        25000);
        $this->menu($org->id, $catNonKopi->id, 'N004', 'Lemon Tea',              18000);
        $this->menu($org->id, $catNonKopi->id, 'N005', 'Strawberry Squash',      22000);
        $this->menu($org->id, $catNonKopi->id, 'N006', 'Es Jeruk Peras',         15000);

        // — Makanan Ringan
        $this->menu($org->id, $catMakanan->id, 'F001', 'Roti Bakar Coklat Keju', 18000);
        $this->menu($org->id, $catMakanan->id, 'F002', 'Kentang Goreng',          20000);
        $this->menu($org->id, $catMakanan->id, 'F003', 'Pisang Goreng Keju',      16000);
        $this->menu($org->id, $catMakanan->id, 'F004', 'Singkong Goreng Crispy',  15000);
        $this->menu($org->id, $catMakanan->id, 'F005', 'Sandwich Club',           27000);

        // — Dessert
        $this->menu($org->id, $catDessert->id, 'D001', 'Puding Coklat',          18000);
        $this->menu($org->id, $catDessert->id, 'D002', 'Bolu Pandan',            16000);
        $this->menu($org->id, $catDessert->id, 'D003', 'Es Krim Vanilla Scoop',  20000);
        $this->menu($org->id, $catDessert->id, 'D004', 'Waffle Madu',            25000);

        // — Nasi & Mie
        $this->menu($org->id, $catFusion->id,  'M001', 'Nasi Goreng Kampung',    28000);
        $this->menu($org->id, $catFusion->id,  'M002', 'Mie Goreng Jawa',        27000);
        $this->menu($org->id, $catFusion->id,  'M003', 'Nasi Bakar Teri',        30000);
        $this->menu($org->id, $catFusion->id,  'M004', 'Kwetiau Goreng Sapi',    32000);

        // ── 8. Meja Makan + QR Code ───────────────────────────────────────
        $tables = [
            ['code' => 'KB-01', 'name' => 'Meja Indoor 1',   'capacity' => 2, 'label' => 'Indoor'],
            ['code' => 'KB-02', 'name' => 'Meja Indoor 2',   'capacity' => 2, 'label' => 'Indoor'],
            ['code' => 'KB-03', 'name' => 'Meja Indoor 3',   'capacity' => 4, 'label' => 'Indoor'],
            ['code' => 'KB-04', 'name' => 'Meja Indoor 4',   'capacity' => 4, 'label' => 'Indoor'],
            ['code' => 'KB-05', 'name' => 'Meja Indoor 5',   'capacity' => 4, 'label' => 'Indoor'],
            ['code' => 'KB-06', 'name' => 'Meja Indoor 6',   'capacity' => 6, 'label' => 'Indoor'],
            ['code' => 'KB-07', 'name' => 'Sofa Corner',     'capacity' => 4, 'label' => 'Indoor – Sofa'],
            ['code' => 'KB-08', 'name' => 'Outdoor 1',       'capacity' => 2, 'label' => 'Outdoor'],
            ['code' => 'KB-09', 'name' => 'Outdoor 2',       'capacity' => 2, 'label' => 'Outdoor'],
            ['code' => 'KB-10', 'name' => 'Outdoor 3',       'capacity' => 4, 'label' => 'Outdoor'],
            ['code' => 'KB-11', 'name' => 'VIP Room',        'capacity' => 8, 'label' => 'VIP'],
            ['code' => 'KB-12', 'name' => 'Bar Counter',     'capacity' => 4, 'label' => 'Bar'],
        ];

        foreach ($tables as $tData) {
            $table = DiningTable::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'code'            => $tData['code'],
                ],
                [
                    'name'           => $tData['name'],
                    'capacity'       => $tData['capacity'],
                    'location_label' => $tData['label'],
                    'status'         => TableStatus::Available,
                ]
            );

            // Pastikan setiap meja punya 1 QR code aktif
            $hasActiveQr = TableQrCode::where('dining_table_id', $table->id)
                ->where('status', QrCodeStatus::Active)
                ->exists();

            if (! $hasActiveQr) {
                $token = Str::random(32);
                TableQrCode::create([
                    'organization_id' => $org->id,
                    'dining_table_id' => $table->id,
                    'qr_token'        => $token,
                    'qr_url'          => "https://santap.id/o/{$org->slug}/t/{$tData['code']}?qr={$token}",
                    'status'          => QrCodeStatus::Active,
                ]);
            }
        }

        $this->command->info('✅ Kobesah Godean seeded — ' . count($tables) . ' tables, 27 menus, 5 categories.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function attachIfMissing(Organization $org, int $userId, string $role): void
    {
        if (! $org->users()->where('user_id', $userId)->exists()) {
            $org->users()->attach($userId, [
                'role_name' => $role,
                'status'    => 'active',
                'joined_at' => now(),
            ]);
        }
    }

    private function category(int $orgId, string $slug, string $name): MenuCategory
    {
        return MenuCategory::firstOrCreate(
            ['organization_id' => $orgId, 'slug' => $slug],
            ['name' => $name]
        );
    }

    private function menu(int $orgId, int $categoryId, string $sku, string $name, int $price): Menu
    {
        $slug = Str::slug($name);

        return Menu::firstOrCreate(
            ['organization_id' => $orgId, 'slug' => $slug],
            [
                'menu_category_id' => $categoryId,
                'name'             => $name,
                'sku'              => $sku,
                'price'            => $price,
                'status'           => MenuStatus::Active,
            ]
        );
    }
}
