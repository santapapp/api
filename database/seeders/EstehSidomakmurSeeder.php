<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillStatus;
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

class EstehSidomakmurSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🍵 Seeding Tenant: Es Teh Sidomakmur...');

        // ================================================================
        // 1. USERS
        // ================================================================
        $owner = User::firstOrCreate(
            ['email' => 'owner@esteh-sidomakmur.id'],
            [
                'name'     => 'Pak Sidomakmur',
                'password' => bcrypt('password'),
            ]
        );

        $cashier1 = User::firstOrCreate(
            ['email' => 'kasir1@esteh-sidomakmur.id'],
            [
                'name'     => 'Rina Kasir',
                'password' => bcrypt('password'),
            ]
        );

        $cashier2 = User::firstOrCreate(
            ['email' => 'kasir2@esteh-sidomakmur.id'],
            [
                'name'     => 'Dimas Kasir',
                'password' => bcrypt('password'),
            ]
        );

        $barista1 = User::firstOrCreate(
            ['email' => 'barista1@esteh-sidomakmur.id'],
            [
                'name'     => 'Mas Bima Barista',
                'password' => bcrypt('password'),
            ]
        );

        // ================================================================
        // 2. ORGANIZATION (TENANT)
        // ================================================================
        $org = Organization::firstOrCreate(
            ['slug' => 'es-teh-sidomakmur'],
            [
                'name'                    => 'Es Teh Sidomakmur',
                'is_active'               => true,
                'logo'                    => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=300',
                'banner'                  => 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=1200',
                'phone'                   => '0812-3456-7890',
                'email'                   => 'info@estehsidomakmur.id',
                'address'                 => 'Jl. Sidomakmur No. 88, Kartasura',
                'city'                    => 'Surakarta',
                'province'                => 'Jawa Tengah',
                'postal_code'             => '57126',
                'latitude'                => -7.56660000,
                'longitude'               => 110.81666000,
                'timezone'                => 'Asia/Jakarta',
                'currency'                => 'IDR',
                'tax_enabled'             => true,
                'tax_rate'                => 10.00,
                'service_charge_enabled'  => false,
                'service_charge_rate'     => 0.00,
                'order_marker_mode'       => 'number',
                'order_marker_max_number' => 100,
                'plan'                    => 'pro',
                'subscription_status'     => 'active',
                'subscription_expires_at' => now()->addYear(),
                'opening_hours'           => [
                    'monday'    => '09:00 - 21:30',
                    'tuesday'   => '09:00 - 21:30',
                    'wednesday' => '09:00 - 21:30',
                    'thursday'  => '09:00 - 21:30',
                    'friday'    => '13:00 - 22:00',
                    'saturday'  => '09:00 - 22:30',
                    'sunday'    => '09:00 - 22:30',
                ],
                'settings'                => [
                    'motto'             => 'Segarnya Keberkahan Nusantara',
                    'wifi_name'         => 'EsTehSidomakmur_Free',
                    'wifi_password'     => 'tehsedap88',
                    'instagram'         => '@estehsidomakmur',
                ],
            ]
        );

        $org->update([
            'logo'   => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=500',
            'banner' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=1600',
        ]);

        // ================================================================
        // 3. MEMBERS
        // ================================================================
        $members = [
            [$owner->id,    'owner'],
            [$cashier1->id, 'cashier'],
            [$cashier2->id, 'cashier'],
            [$barista1->id, 'kitchen'],
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
            'Meja 01', 'Meja 02', 'Meja 03', 'Meja 04', 'Meja 05', 'Meja 06',
            'Outdoor 01', 'Outdoor 02', 'Takeaway / Bar',
        ];

        $tables = [];
        foreach ($tableNames as $index => $name) {
            $tables[$name] = DiningTable::firstOrCreate(
                ['organization_id' => $org->id, 'name' => $name],
                [
                    'code'     => 'T-0' . ($index + 1),
                    'capacity' => Str::contains($name, 'Outdoor') ? 6 : 4,
                    'location' => Str::contains($name, 'Outdoor') ? 'Outdoor Garden' : 'Indoor Area',
                    'qr_token' => Str::random(32),
                ]
            );
        }

        // ================================================================
        // 5. MENU TREE (Inspirasi Menu Es Teh Nusantara)
        // ================================================================

        // ── A. TEH ORIGINAL & KLASIK SERIES ──────────────────────────────

        // 1. Es Teh Solo Original
        $esTehSolo = $this->product(
            $org->id,
            'Es Teh Solo Original',
            5000,
            1,
            'Teh melati khas Solo yang pekat, harum, dan manis legi yang khas.',
            'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600'
        );
        
        $gUkuran = $this->variantGroup($org->id, $esTehSolo->id, 'Ukuran Porsi', 1, true, 1, 1);
        $vReg = $this->variant($org->id, $gUkuran->id, 'Reguler (Medium)', 0, 1);
        $vJum = $this->variant($org->id, $gUkuran->id, 'Jumbo (Large)', 3000, 2);

        $gGula = $this->variantGroup($org->id, $esTehSolo->id, 'Level Gula (Sugar Level)', 2, true, 1, 1);
        $this->variant($org->id, $gGula->id, 'Normal Sugar (100%)', 0, 1);
        $this->variant($org->id, $gGula->id, 'Less Sugar (50%)', 0, 2);
        $this->variant($org->id, $gGula->id, 'Extra Sugar (120%)', 0, 3);
        $this->variant($org->id, $gGula->id, 'Tanpa Gula (0%)', 0, 4);

        $gEs = $this->variantGroup($org->id, $esTehSolo->id, 'Level Es (Ice Level)', 3, true, 1, 1);
        $this->variant($org->id, $gEs->id, 'Normal Ice', 0, 1);
        $this->variant($org->id, $gEs->id, 'Less Ice', 0, 2);
        $this->variant($org->id, $gEs->id, 'Extra Ice', 0, 3);
        $this->variant($org->id, $gEs->id, 'No Ice / Hangat', 0, 4);

        $gTopping = $this->addonGroup($org->id, $esTehSolo->id, 'Topping Extra', 4, false, 0, 3);
        $aCincau = $this->addon($org->id, $gTopping->id, 'Cincau Hitam', 2000, 1);
        $aBobaM = $this->addon($org->id, $gTopping->id, 'Popping Boba Mango', 3000, 2);
        $aJellyL = $this->addon($org->id, $gTopping->id, 'Jelly Lychee', 2500, 3);
        $aCreamC = $this->addon($org->id, $gTopping->id, 'Cream Cheese Macchiato', 4000, 4);
        $aSelasih = $this->addon($org->id, $gTopping->id, 'Biji Selasih', 1500, 5);

        // 2. Es Teh Kampul Solo
        $esTehKampul = $this->product(
            $org->id,
            'Es Teh Kampul Solo',
            7000,
            2,
            'Teh wangi khas Solo dipadukan dengan perasan dan irisan jeruk nipis segar.',
            'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600'
        );
        $gkUkuran = $this->variantGroup($org->id, $esTehKampul->id, 'Ukuran Porsi', 1, true, 1, 1);
        $this->variant($org->id, $gkUkuran->id, 'Reguler (Medium)', 0, 1);
        $this->variant($org->id, $gkUkuran->id, 'Jumbo (Large)', 3000, 2);

        $gkGula = $this->variantGroup($org->id, $esTehKampul->id, 'Level Gula', 2, true, 1, 1);
        $this->variant($org->id, $gkGula->id, 'Normal Sugar (100%)', 0, 1);
        $this->variant($org->id, $gkGula->id, 'Less Sugar (50%)', 0, 2);

        $gkAddon = $this->addonGroup($org->id, $esTehKampul->id, 'Topping Extra', 3, false, 0, 2);
        $this->addon($org->id, $gkAddon->id, 'Extra Irisan Jeruk Nipis', 2000, 1);
        $this->addon($org->id, $gkAddon->id, 'Cincau Hitam', 2000, 2);
        $this->addon($org->id, $gkAddon->id, 'Biji Selasih', 1500, 3);

        // 3. Es Teh Lemon (Lemon Tea Nusantara)
        $esTehLemon = $this->product(
            $org->id,
            'Es Teh Lemon Nusantara',
            8000,
            3,
            'Kombinasi asam lemon segar dan teh racikan khas Nusantara.',
            'https://images.unsplash.com/photo-1599390719602-53697eb1df39?w=600'
        );
        $glUkuran = $this->variantGroup($org->id, $esTehLemon->id, 'Ukuran Porsi', 1, true, 1, 1);
        $this->variant($org->id, $glUkuran->id, 'Reguler (Medium)', 0, 1);
        $this->variant($org->id, $glUkuran->id, 'Jumbo (Large)', 3000, 2);

        // 4. Es Teh Lychee (Leci Tea Spesial)
        $esTehLeci = $this->product(
            $org->id,
            'Es Teh Leci Tea Spesial',
            10000,
            4,
            'Teh aroma leci segar dilengkapi topping buah leci asli.',
            'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600'
        );
        $glcUkuran = $this->variantGroup($org->id, $esTehLeci->id, 'Ukuran Porsi', 1, true, 1, 1);
        $this->variant($org->id, $glcUkuran->id, 'Reguler (Medium)', 0, 1);
        $this->variant($org->id, $glcUkuran->id, 'Jumbo (Large)', 4000, 2);
        $glcAddon = $this->addonGroup($org->id, $esTehLeci->id, 'Topping Extra', 2, false, 0, 2);
        $this->addon($org->id, $glcAddon->id, 'Extra Buah Leci (2 pcs)', 3500, 1);
        $this->addon($org->id, $glcAddon->id, 'Jelly Lychee', 2500, 2);
        $this->addon($org->id, $glcAddon->id, 'Cream Cheese', 4000, 3);

        // ── B. FRUITY & REFRESHING SERIES ──────────────────────────────────

        // 5. Es Teh Mangga (Mango Tea)
        $esTehMango = $this->product(
            $org->id,
            'Es Teh Mangga (Mango Tea)',
            9000,
            5,
            'Rasa manis tropis buah mangga dikombinasikan dengan teh wangi.',
            'https://images.unsplash.com/photo-1546173159-315724a31696?w=600'
        );
        $gmUkuran = $this->variantGroup($org->id, $esTehMango->id, 'Ukuran Porsi', 1, true, 1, 1);
        $this->variant($org->id, $gmUkuran->id, 'Reguler (Medium)', 0, 1);
        $this->variant($org->id, $gmUkuran->id, 'Jumbo (Large)', 3000, 2);
        $gmAddon = $this->addonGroup($org->id, $esTehMango->id, 'Topping Recommended', 2, false, 0, 2);
        $this->addon($org->id, $gmAddon->id, 'Popping Boba Mango', 3000, 1);
        $this->addon($org->id, $gmAddon->id, 'Cream Cheese', 4000, 2);

        // 6. Es Teh Markisa (Passion Fruit Tea)
        $this->product(
            $org->id,
            'Es Teh Markisa Nusantara',
            9000,
            6,
            'Sensasi asam manis sari buah markisa asli yang sangat menyegarkan.',
            'https://images.unsplash.com/photo-1621263764928-df1444c5e859?w=600'
        );

        // 7. Es Teh Strawberry Macchiato
        $this->product(
            $org->id,
            'Es Teh Strawberry Macchiato',
            12000,
            7,
            'Teh rasa strawberry segar dilapisi krim macchiato gurih manis di atasnya.',
            'https://images.unsplash.com/photo-1553787499-6f9133860278?w=600'
        );

        // ── C. CREAMY & MILK TEA SERIES ─────────────────────────────────────

        // 8. Es Teh Milk Tea Nusantara (Teh Susu Original)
        $esTehMilk = $this->product(
            $org->id,
            'Es Teh Milk Tea Nusantara',
            10000,
            8,
            'Perpaduan sempurna racikan teh wangi dan susu creamy lembut.',
            'https://images.unsplash.com/photo-1558857563-b371033873b8?w=600'
        );
        $gmtUkuran = $this->variantGroup($org->id, $esTehMilk->id, 'Ukuran Porsi', 1, true, 1, 1);
        $this->variant($org->id, $gmtUkuran->id, 'Reguler (Medium)', 0, 1);
        $this->variant($org->id, $gmtUkuran->id, 'Jumbo (Large)', 4000, 2);
        $gmtAddon = $this->addonGroup($org->id, $esTehMilk->id, 'Topping Favorit', 2, false, 0, 3);
        $aBobaP = $this->addon($org->id, $gmtAddon->id, 'Boba Pearl Chewy', 3000, 1);
        $this->addon($org->id, $gmtAddon->id, 'Cincau Hitam', 2000, 2);
        $this->addon($org->id, $gmtAddon->id, 'Cream Cheese Macchiato', 4000, 3);

        // 9. Es Teh Taro Milk
        $this->product(
            $org->id,
            'Es Teh Taro Milk Creamy',
            12000,
            9,
            'Flavor taro (talas manis) bertemu dengan racikan teh dan susu manis.',
            'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=600'
        );

        // 10. Es Teh Red Velvet Cheese
        $this->product(
            $org->id,
            'Es Teh Red Velvet Cheese',
            13000,
            10,
            'Sensasi kue red velvet gurih dengan lapisan foam cheese melimpah.',
            'https://images.unsplash.com/photo-1579954115545-aad51642a697?w=600'
        );

        // 11. Es Teh Matcha Latte
        $this->product(
            $org->id,
            'Es Teh Matcha Latte',
            14000,
            11,
            'Matcha jepang kualitas pilihan berpadu gurihnya susu dan teh Nusantara.',
            'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=600'
        );

        // 12. Es Teh Choco Milo Nusantara
        $this->product(
            $org->id,
            'Es Teh Choco Milo Nusantara',
            13000,
            12,
            'Kombinasi cokelat Milo melimpah dan rasa teh wangi khas Sidomakmur.',
            'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600'
        );

        // ── D. SNACK & CEMILAN PENDAMPING ES TEH ────────────────────────────

        // 13. Roti Bakar Cokelat Keju
        $rotiBakar = $this->product(
            $org->id,
            'Roti Bakar Cokelat Keju',
            12000,
            13,
            'Roti bakar empuk renyah dengan isian cokelat meises dan parutan keju keju melimpah.',
            'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=600'
        );
        $grbTop = $this->variantGroup($org->id, $rotiBakar->id, 'Topping Utama', 1, true, 1, 1);
        $this->variant($org->id, $grbTop->id, 'Mix Cokelat Keju Spesial', 0, 1);
        $this->variant($org->id, $grbTop->id, 'Full Keju Susu', 0, 2);
        $this->variant($org->id, $grbTop->id, 'Full Cokelat Meises', 0, 3);

        // 14. Pisang Goreng Keju Crispy
        $pisangGoreng = $this->product(
            $org->id,
            'Pisang Goreng Keju Crispy',
            10000,
            14,
            'Pisang goreng crispy manis legit disajikan dengan taburan keju dan susu kental manis.',
            'https://images.unsplash.com/photo-1603532648955-039310d9ed75?w=600'
        );

        // 15. Cireng Rujak Pedas
        $cireng = $this->product(
            $org->id,
            'Cireng Rujak Pedas',
            10000,
            15,
            'Cireng renyah di luar kenyal di dalam disajikan bersama bumbu rujak pedas manis.',
            'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600'
        );
        $gcrLevel = $this->variantGroup($org->id, $cireng->id, 'Level Sambal Rujak', 1, true, 1, 1);
        $this->variant($org->id, $gcrLevel->id, 'Level Sedang (Pedas Manis)', 0, 1);
        $this->variant($org->id, $gcrLevel->id, 'Level Pedas Mampus', 1000, 2);

        // 16. French Fries (Kentang Goreng)
        $fries = $this->product(
            $org->id,
            'French Fries (Kentang Goreng)',
            10000,
            16,
            'Kentang goreng stik renyah gurih.',
            'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=600'
        );
        $gfrBumbu = $this->variantGroup($org->id, $fries->id, 'Bumbu Tabur', 1, true, 1, 1);
        $this->variant($org->id, $gfrBumbu->id, 'Balado Pedas Sweet', 0, 1);
        $this->variant($org->id, $gfrBumbu->id, 'Keju Gurih', 0, 2);
        $this->variant($org->id, $gfrBumbu->id, 'BBQ Smokey', 0, 3);
        $this->variant($org->id, $gfrBumbu->id, 'Original Salted', 0, 4);

        // 17. Dimsum Ayam Mentai (4 Pcs)
        $dimsum = $this->product(
            $org->id,
            'Dimsum Ayam Mentai (4 Pcs)',
            15000,
            17,
            'Dimsum ayam olahan lembut dibalur saus mentai gurih dan di-torch.',
            'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?w=600'
        );
        $gdsAddon = $this->addonGroup($org->id, $dimsum->id, 'Saus & Topping Extra', 1, false, 0, 2);
        $this->addon($org->id, $gdsAddon->id, 'Extra Saus Mentai Torch', 3000, 1);
        $this->addon($org->id, $gdsAddon->id, 'Chili Oil Pedas Gurih', 2000, 2);

        $this->command->info('  ✅ Menu Tree (Es Teh Nusantara style) berhasil dibuat');

        // ================================================================
        // 6. SAMPLE TRANSACTIONS (FOR DISSEMINATION PRESENTATION STUDY CASE)
        // ================================================================

        // Order 1: Closed Cash Order
        $this->createClosedCashOrder(
            $org,
            $tables['Meja 01'],
            $cashier1,
            [
                [$esTehSolo, 2, 'Gula kurang manis, es sedang', [$vJum, $aCincau]],
                [$rotiBakar, 1, 'Extra keju parut', []],
            ],
            35000,
            45
        );

        // Order 2: Closed QRIS Order
        $this->createClosedQrisOrder(
            $org,
            $tables['Meja 02'],
            $cashier1,
            [
                [$esTehMilk, 2, 'Boba empuk', [$aBobaP]],
                [$dimsum, 1, 'Pedas mentai torch', []],
            ],
            30
        );

        // Order 3: Open Table Order (Ongoing/Active Order)
        $this->createOpenOrder(
            $org,
            $tables['Meja 03'],
            $cashier2,
            [
                [$esTehKampul, 2, 'Irisan jeruk jangan terlalu banyak', []],
                [$pisangGoreng, 1, 'Susu kental manis banyakin', []],
                [$cireng, 1, 'Sambal rujak terpisah', []],
            ],
            10,
            OrderType::TableOrder
        );

        $this->command->info('  ✅ Sample Orders berhasil di-generate');
        $this->command->info('');
        $this->command->info('================================================');
        $this->command->info('🎉 Seeder Es Teh Sidomakmur Selesai!');
        $this->command->info('================================================');
        $this->command->info('📋 INFORMASI LOGIN & TENANT:');
        $this->command->info("  Tenant Name : Es Teh Sidomakmur");
        $this->command->info("  Tenant Slug : es-teh-sidomakmur");
        $this->command->info("  Tenant ID   : {$org->id}");
        $this->command->info("  Owner       : owner@esteh-sidomakmur.id / password");
        $this->command->info("  Kasir 1     : kasir1@esteh-sidomakmur.id / password");
        $this->command->info("  Kasir 2     : kasir2@esteh-sidomakmur.id / password");
        $this->command->info("  Barista     : barista1@esteh-sidomakmur.id / password");
        $this->command->info('================================================');
    }

    // ====================================================================
    // HELPERS: MENU CREATION
    // ====================================================================

    private function product(int $orgId, string $name, int $price, int $sort, string $description = '', ?string $image = null): Menu
    {
        $menu = Menu::firstOrCreate(
            ['organization_id' => $orgId, 'name' => $name, 'parent_id' => null],
            [
                'type'        => 'product',
                'price'       => $price,
                'description' => $description,
                'image'       => $image,
                'sort_order'  => $sort,
            ]
        );

        if ($image && $menu->image !== $image) {
            $menu->update(['image' => $image]);
        }

        return $menu;
    }

    private function variantGroup(
        int $orgId,
        int $parentId,
        string $name,
        int $sort,
        bool $isRequired = true,
        int $minSelect = 1,
        int $maxSelect = 1
    ): Menu {
        return Menu::firstOrCreate(
            ['organization_id' => $orgId, 'parent_id' => $parentId, 'name' => $name, 'type' => 'variant_group'],
            [
                'price'       => 0,
                'is_required' => $isRequired,
                'min_select'  => $minSelect,
                'max_select'  => $maxSelect,
                'sort_order'  => $sort,
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

    private function addonGroup(
        int $orgId,
        int $parentId,
        string $name,
        int $sort,
        bool $isRequired = false,
        int $minSelect = 0,
        int $maxSelect = 5
    ): Menu {
        return Menu::firstOrCreate(
            ['organization_id' => $orgId, 'parent_id' => $parentId, 'name' => $name, 'type' => 'addon_group'],
            [
                'price'       => 0,
                'is_required' => $isRequired,
                'min_select'  => $minSelect,
                'max_select'  => $maxSelect,
                'sort_order'  => $sort,
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
    // HELPERS: ORDER CREATION
    // ====================================================================

    private function createClosedCashOrder(
        Organization $org,
        DiningTable $table,
        User $createdBy,
        array $items,
        int $paymentAmount,
        int $minutesAgo
    ): Order {
        $openedAt = now()->subMinutes($minutesAgo);
        $closedAt = $openedAt->copy()->addMinutes(rand(15, 30));

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
            'payment_method'    => 'cash',
            'payment_reference' => 'CASH-' . strtoupper(Str::random(8)),
            'opened_at'         => $openedAt,
            'paid_at'           => $closedAt,
            'closed_at'         => $closedAt,
        ]);

        $this->attachItems($order, $items, 'served');
        $order->recalculate();
        $order->update(['payment_amount' => (float) $paymentAmount]);

        return $order;
    }

    private function createClosedQrisOrder(
        Organization $org,
        DiningTable $table,
        User $createdBy,
        array $items,
        int $minutesAgo
    ): Order {
        $openedAt = now()->subMinutes($minutesAgo);
        $closedAt = $openedAt->copy()->addMinutes(rand(10, 20));

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
            'payment_reference' => 'QRIS-' . strtoupper(Str::random(10)),
            'opened_at'         => $openedAt,
            'paid_at'           => $closedAt,
            'closed_at'         => $closedAt,
        ]);

        $this->attachItems($order, $items, 'served');
        $order->recalculate();
        $order->update(['payment_amount' => (float) $order->total_amount]);

        return $order;
    }

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

    private function attachItems(Order $order, array $items, string $itemStatus): void
    {
        foreach ($items as [$menu, $qty, $note, $children]) {
            /** @var Menu $menu */
            OrderItem::create([
                'order_id'    => $order->id,
                'menu_id'     => $menu->id,
                'name'        => $menu->name,
                'base_price'  => $menu->price,
                'unit_price'  => $menu->price,
                'price'       => $menu->price,
                'quantity'    => $qty,
                'subtotal'    => round((float) $menu->price * $qty, 2),
                'item_status' => $itemStatus,
                'note'        => $note,
            ]);
        }
    }
}
