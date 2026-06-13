<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\BillStatus;
use App\Enums\MenuType;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AddItemsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private DiningTable $table;

    private Order $order;

    private string $publicToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test Warung',
            'slug' => 'test-warung',
            'is_active' => true,
        ]);

        $this->table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name' => 'Meja 1',
            'qr_token' => Str::random(32),
            'is_active' => true,
        ]);

        $this->publicToken = Str::random(32);

        $this->order = Order::create([
            'order_number' => 'ORD-TEST-001',
            'public_token' => $this->publicToken,
            'organization_id' => $this->org->id,
            'dining_table_id' => $this->table->id,
            'order_type' => OrderType::OpenBill,
            'bill_status' => BillStatus::Open,
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'tax_rate_snapshot' => 0,
            'service_charge_rate_snapshot' => 0,
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'total_amount' => 0,
            'payment_amount' => 0,
            'change_amount' => 0,
            'opened_at' => now(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeProduct(string $name = 'Jus Mangga', float $price = 15000): Menu
    {
        return Menu::create([
            'organization_id' => $this->org->id,
            'type' => MenuType::Product,
            'name' => $name,
            'price' => $price,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function makeVariantGroup(
        Menu $product,
        string $name = 'Pilihan Rasa',
        bool $required = false,
        int $min = 0,
        int $max = 1
    ): Menu {
        return Menu::create([
            'organization_id' => $this->org->id,
            'parent_id' => $product->id,
            'type' => MenuType::VariantGroup,
            'name' => $name,
            'price' => 0,
            'is_available' => true,
            'is_required' => $required,
            'min_select' => $min,
            'max_select' => $max,
            'sort_order' => 1,
        ]);
    }

    private function makeVariant(Menu $group, string $name = 'Mangga', float $price = 0): Menu
    {
        return Menu::create([
            'organization_id' => $this->org->id,
            'parent_id' => $group->id,
            'type' => MenuType::Variant,
            'name' => $name,
            'price' => $price,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function postItems(array $items): TestResponse
    {
        return $this->postJson(
            uri: route('api.v1.customer.order.add-items'),
            data: ['items' => $items],
            headers: ['X-Public-Token' => $this->publicToken]
        );
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_adds_product_without_variants(): void
    {
        $product = $this->makeProduct('Nasi Goreng', 20000);

        $response = $this->postItems([
            ['menu_id' => $product->id, 'quantity' => 2],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->order->id,
            'menu_id' => $product->id,
            'name' => 'Nasi Goreng',
            'base_price' => '20000.00',
            'variant_total' => '0.00',
            'unit_price' => '20000.00',
            'quantity' => 2,
            'subtotal' => '40000.00',
        ]);

        // Tidak ada table order_item_variants — snapshot ada di metadata
        $item = $response->json('data.items.0');
        $this->assertEquals('20000.00', $item['base_price']);
        $this->assertEquals('0.00', $item['variant_total']);
        $this->assertEquals('20000.00', $item['unit_price']);
        $this->assertEquals('40000.00', $item['subtotal']);
        $this->assertEmpty($item['selected_options']);
    }

    public function test_open_bill_repeat_order_creates_incrementing_item_batches(): void
    {
        $cashier = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id' => $cashier->id,
            'role' => 'cashier',
        ]);

        $nasi = $this->makeProduct('Nasi Goreng', 20000);
        $teh = $this->makeProduct('Es Teh', 5000);
        $kopi = $this->makeProduct('Kopi Susu', 18000);

        $first = $this->postItems([
            ['menu_id' => $nasi->id, 'quantity' => 1],
            ['menu_id' => $teh->id, 'quantity' => 2],
        ]);

        $first->assertOk()
            ->assertJsonPath('batch.batch_number', 1)
            ->assertJsonPath('batch.items_count', 2)
            ->assertJsonPath('data.summary.batch_count', 1)
            ->assertJsonPath('data.item_batches.0.label', 'Pesanan #1');

        $firstItems = OrderItem::where('order_id', $this->order->id)
            ->where('batch_number', 1)
            ->get();

        $this->assertCount(2, $firstItems);
        $this->assertCount(1, $firstItems->pluck('batch_uuid')->unique());
        $this->assertCount(1, $firstItems->pluck('submitted_at')->unique());
        $this->assertNotNull($firstItems->first()->batch_uuid);
        $this->assertNotNull($firstItems->first()->submitted_at);

        $second = $this->postItems([
            ['menu_id' => $kopi->id, 'quantity' => 2],
        ]);

        $second->assertOk()
            ->assertJsonPath('batch.batch_number', 2)
            ->assertJsonPath('batch.items_count', 1)
            ->assertJsonPath('data.summary.batch_count', 2)
            ->assertJsonPath('data.item_batches.1.label', 'Pesanan #2')
            ->assertJsonPath('data.item_batches.1.total_amount', 36000);

        $this->withHeader('X-Public-Token', $this->publicToken)
            ->getJson(route('api.v1.customer.order.show'))
            ->assertOk()
            ->assertJsonPath('data.summary.items_count', 3)
            ->assertJsonPath('data.summary.batch_count', 2)
            ->assertJsonPath('data.item_batches.0.label', 'Pesanan #1')
            ->assertJsonPath('data.item_batches.1.label', 'Pesanan #2');

        $this->actingAs($cashier)
            ->withHeader('X-Org-ID', (string) $this->org->id)
            ->getJson(route('api.v1.cashier.orders.index', [
                'order_type' => OrderType::OpenBill->value,
                'bill_status' => BillStatus::Open->value,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.batch_count', 2)
            ->assertJsonPath('data.0.latest_batch.batch_number', 2)
            ->assertJsonPath('data.0.latest_batch.label', 'Pesanan #2');
    }

    public function test_legacy_open_bill_items_without_batch_are_grouped_as_first_batch(): void
    {
        $product = $this->makeProduct('Nasi Goreng', 20000);

        OrderItem::create([
            'order_id' => $this->order->id,
            'menu_id' => $product->id,
            'item_type' => 'product',
            'name' => $product->name,
            'base_price' => 20000,
            'variant_total' => 0,
            'unit_price' => 20000,
            'price' => 20000,
            'quantity' => 1,
            'subtotal' => 20000,
        ]);

        $this->withHeader('X-Public-Token', $this->publicToken)
            ->getJson(route('api.v1.customer.order.show'))
            ->assertOk()
            ->assertJsonPath('data.summary.batch_count', 1)
            ->assertJsonPath('data.item_batches.0.batch_number', 1)
            ->assertJsonPath('data.item_batches.0.label', 'Pesanan #1')
            ->assertJsonPath('data.item_batches.0.total_amount', 20000);
    }

    public function test_adds_product_with_variant_and_calculates_price(): void
    {
        $product = $this->makeProduct('Jus Buah', 15000);
        $group = $this->makeVariantGroup($product, 'Pilihan Rasa');
        $variant = $this->makeVariant($group, 'Mangga', 3000);

        $response = $this->postItems([[
            'menu_id' => $product->id,
            'quantity' => 2,
            'selected_variants' => [
                ['variant_group_id' => $group->id, 'variant_id' => $variant->id],
            ],
        ]]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('order_items', [
            'menu_id' => $product->id,
            'base_price' => '15000.00',
            'variant_total' => '3000.00',
            'unit_price' => '18000.00',
            'quantity' => 2,
            'subtotal' => '36000.00',
        ]);

        // Snapshot tersimpan di metadata.selected_options
        $item = $response->json('data.items.0');
        $opt = $item['selected_options'][0];
        $this->assertEquals('Pilihan Rasa', $opt['group_name']);
        $this->assertEquals('Mangga', $opt['option_name']);
        $this->assertEquals(3000, $opt['price_delta']);
    }

    public function test_rejects_menu_that_is_not_a_product(): void
    {
        $product = $this->makeProduct();
        $group = $this->makeVariantGroup($product);

        $response = $this->postItems([
            ['menu_id' => $group->id, 'quantity' => 1],
        ]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $errorMsg = collect($errors)->flatten()->implode(' ');
        $this->assertStringContainsString('bukan type product', $errorMsg);
    }

    public function test_rejects_variant_group_not_belonging_to_product(): void
    {
        $product1 = $this->makeProduct('Produk A', 10000);
        $product2 = $this->makeProduct('Produk B', 10000);
        $groupB = $this->makeVariantGroup($product2, 'Rasa B');
        $variantB = $this->makeVariant($groupB, 'Vanilla');

        $response = $this->postItems([[
            'menu_id' => $product1->id,
            'quantity' => 1,
            'selected_variants' => [
                ['variant_group_id' => $groupB->id, 'variant_id' => $variantB->id],
            ],
        ]]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $errorMsg = collect($errors)->flatten()->implode(' ');
        $this->assertStringContainsString('tidak ditemukan pada produk ini', $errorMsg);
    }

    public function test_rejects_missing_required_variant_group(): void
    {
        $product = $this->makeProduct('Kopi', 10000);
        $this->makeVariantGroup($product, 'Tingkat Gula', required: true, min: 1, max: 1);

        $response = $this->postItems([
            ['menu_id' => $product->id, 'quantity' => 1],
        ]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $errorMsg = collect($errors)->flatten()->implode(' ');
        $this->assertStringContainsString('wajib dipilih', $errorMsg);
    }

    public function test_rejects_exceeding_max_select(): void
    {
        $product = $this->makeProduct('Pizza', 50000);
        $group = $this->makeVariantGroup($product, 'Topping', max: 1);
        $v1 = $this->makeVariant($group, 'Keju', 5000);
        $v2 = $this->makeVariant($group, 'Pepperoni', 7000);

        $response = $this->postItems([[
            'menu_id' => $product->id,
            'quantity' => 1,
            'selected_variants' => [
                ['variant_group_id' => $group->id, 'variant_id' => $v1->id],
                ['variant_group_id' => $group->id, 'variant_id' => $v2->id],
            ],
        ]]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $errorMsg = collect($errors)->flatten()->implode(' ');
        $this->assertStringContainsString('maksimal', $errorMsg);
    }

    public function test_rejects_unavailable_variant(): void
    {
        $product = $this->makeProduct('Teh', 8000);
        $group = $this->makeVariantGroup($product, 'Suhu');
        Menu::create([
            'organization_id' => $this->org->id,
            'parent_id' => $group->id,
            'type' => MenuType::Variant,
            'name' => 'Panas',
            'price' => 0,
            'is_available' => false,
            'sort_order' => 1,
        ]);

        $unavailableVariant = Menu::where('name', 'Panas')->first();

        $response = $this->postItems([[
            'menu_id' => $product->id,
            'quantity' => 1,
            'selected_variants' => [
                ['variant_group_id' => $group->id, 'variant_id' => $unavailableVariant->id],
            ],
        ]]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $errorMsg = collect($errors)->flatten()->implode(' ');
        $this->assertStringContainsString('tidak ditemukan pada group ini', $errorMsg);
    }

    public function test_calculates_correctly_for_multiple_variants(): void
    {
        $product = $this->makeProduct('Es Kopi', 18000);
        $group1 = $this->makeVariantGroup($product, 'Tingkat Manis', max: 1);
        $group2 = $this->makeVariantGroup($product, 'Ukuran', max: 1);
        $v1 = $this->makeVariant($group1, 'Setengah Manis', 0);
        $v2 = $this->makeVariant($group2, 'Large', 5000);

        $response = $this->postItems([[
            'menu_id' => $product->id,
            'quantity' => 3,
            'notes' => 'Kurangi es',
            'selected_variants' => [
                ['variant_group_id' => $group1->id, 'variant_id' => $v1->id],
                ['variant_group_id' => $group2->id, 'variant_id' => $v2->id],
            ],
        ]]);

        $response->assertStatus(200);

        // base:18000 + variant_total:5000 = unit:23000 × qty:3 = subtotal:69000
        $this->assertDatabaseHas('order_items', [
            'base_price' => '18000.00',
            'variant_total' => '5000.00',
            'unit_price' => '23000.00',
            'subtotal' => '69000.00',
            'note' => 'Kurangi es',
        ]);

        // Dua selected_options tersimpan di metadata
        $item = $response->json('data.items.0');
        $this->assertCount(2, $item['selected_options']);
    }

    public function test_rejects_menu_from_different_organization(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Org Lain',
            'slug' => 'org-lain',
            'is_active' => true,
        ]);

        $otherProduct = Menu::create([
            'organization_id' => $otherOrg->id,
            'type' => MenuType::Product,
            'name' => 'Produk Org Lain',
            'price' => 10000,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $response = $this->postItems([
            ['menu_id' => $otherProduct->id, 'quantity' => 1],
        ]);

        $response->assertStatus(422);
    }

    public function test_supports_both_note_and_notes_fields(): void
    {
        $product = $this->makeProduct('Bakso', 15000);

        $r1 = $this->postItems([
            ['menu_id' => $product->id, 'quantity' => 1, 'note' => 'Pedas'],
        ]);
        $r1->assertStatus(200);
        $this->assertDatabaseHas('order_items', ['note' => 'Pedas']);

        $r2 = $this->postItems([
            ['menu_id' => $product->id, 'quantity' => 1, 'notes' => 'Ekstra kuah'],
        ]);
        $r2->assertStatus(200);
        $this->assertDatabaseHas('order_items', ['note' => 'Ekstra kuah']);
    }
}
