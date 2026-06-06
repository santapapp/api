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
use App\Models\Organization;
use App\Services\QrisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TableOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private DiningTable  $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'                   => 'Test Warung',
            'slug'                   => 'test-warung',
            'is_active'              => true,
            'tax_enabled'            => true,
            'tax_rate'               => 11.00,
            'service_charge_enabled' => true,
            'service_charge_rate'    => 5.00,
        ]);

        $this->table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name'            => 'Meja 1',
            'code'            => 'T1',
            'qr_token'        => Str::random(32),
            'is_active'       => true,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeProduct(string $name = 'Nasi Goreng', float $price = 20000): Menu
    {
        return Menu::create([
            'organization_id' => $this->org->id,
            'type'            => MenuType::Product,
            'name'            => $name,
            'price'           => $price,
            'is_available'    => true,
            'sort_order'      => 1,
        ]);
    }

    private function mockQrisCreate(): void
    {
        $mock = Mockery::mock(QrisService::class);
        $mock->shouldReceive('create')
            ->andReturn([
                'data' => [
                    'actions'   => [['url' => 'https://qris.example/qr.png']],
                    'qr_string' => 'QR-STRING-DATA',
                ],
            ]);
        $mock->shouldReceive('check')->andReturn([
            'paid'               => false,
            'status'             => 'pending',
            'transaction_status' => 'pending',
            'raw'                => [],
        ]);
        $mock->shouldReceive('cancel')->andReturn(['status' => 'cancelled']);
        $this->app->instance(QrisService::class, $mock);
    }

    // ── A. Scan table ───────────────────────────────────────────────────────

    public function test_scan_table_returns_lookup_without_creating_order_or_session(): void
    {
        $response = $this->getJson(route('api.v1.customer.table.scan', $this->table->qr_token));

        $response->assertStatus(200)
            ->assertJsonPath('data.table.code', 'T1')
            ->assertJsonPath('data.organization.slug', 'test-warung');

        // Tidak ada order yang dibuat oleh scan.
        $this->assertDatabaseCount('orders', 0);
    }

    // ── B. Create table order success ────────────────────────────────────────

    public function test_create_table_order_success(): void
    {
        $this->mockQrisCreate();
        $product = $this->makeProduct('Nasi Goreng', 20000);

        $response = $this->postJson(route('api.v1.customer.order.create'), [
            'qr_token' => $this->table->qr_token,
            'items'    => [
                ['menu_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.order_type', OrderType::TableOrder->value)
            ->assertJsonPath('data.bill_status', BillStatus::None->value)
            ->assertJsonPath('data.order_status', OrderStatus::Pending->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.payment_method', 'qris');

        $this->assertNotEmpty($response->json('data.order_number'));
        $this->assertNotEmpty($response->json('data.public_token'));
        $this->assertNotEmpty($response->json('data.payment_expires_at'));
        $this->assertNotEmpty($response->json('data.qris_data.qr_url'));
        $this->assertNotEmpty($response->json('data.qris_data.payment_reference'));

        $this->assertDatabaseHas('orders', [
            'order_number'   => $response->json('data.order_number'),
            'order_type'     => OrderType::TableOrder->value,
            'bill_status'    => BillStatus::None->value,
            'payment_status' => PaymentStatus::Pending->value,
        ]);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_create_table_order_saves_selected_options_in_metadata(): void
    {
        $this->mockQrisCreate();
        $product = $this->makeProduct('Jus Buah', 15000);
        $group   = Menu::create([
            'organization_id' => $this->org->id,
            'parent_id'       => $product->id,
            'type'            => MenuType::VariantGroup,
            'name'            => 'Rasa',
            'price'           => 0,
            'is_available'    => true,
            'is_required'     => false,
            'min_select'      => 0,
            'max_select'      => 1,
            'sort_order'      => 1,
        ]);
        $variant = Menu::create([
            'organization_id' => $this->org->id,
            'parent_id'       => $group->id,
            'type'            => MenuType::Variant,
            'name'            => 'Mangga',
            'price'           => 3000,
            'is_available'    => true,
            'sort_order'      => 1,
        ]);

        $response = $this->postJson(route('api.v1.customer.order.create'), [
            'qr_token' => $this->table->qr_token,
            'items'    => [[
                'menu_id'           => $product->id,
                'quantity'          => 1,
                'selected_variants' => [
                    ['variant_group_id' => $group->id, 'variant_id' => $variant->id],
                ],
            ]],
        ]);

        $response->assertStatus(201);

        $order = Order::first();
        $item  = $order->items()->first();
        $this->assertEquals('Rasa', $item->metadata['selected_options'][0]['group_name']);
        $this->assertEquals('Mangga', $item->metadata['selected_options'][0]['option_name']);
    }

    // ── C. QRIS fail → rollback ──────────────────────────────────────────────

    public function test_create_table_order_rolls_back_when_qris_fails(): void
    {
        $mock = Mockery::mock(QrisService::class);
        $mock->shouldReceive('create')->andThrow(new \RuntimeException('QRIS down'));
        $this->app->instance(QrisService::class, $mock);

        $product = $this->makeProduct('Nasi Goreng', 20000);

        $response = $this->postJson(route('api.v1.customer.order.create'), [
            'qr_token' => $this->table->qr_token,
            'items'    => [
                ['menu_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Gagal membuat pesanan. Silakan coba lagi.');

        // Tidak ada order/order_items yang menggantung.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    // ── D. Public tracking ───────────────────────────────────────────────────

    public function test_public_tracking_by_order_number_and_public_token(): void
    {
        $order = $this->makeTableOrder();

        $byNumber = $this->getJson(route('api.v1.customer.public.order.show', $order->order_number));
        $byNumber->assertStatus(200)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.bill_status', BillStatus::None->value)
            ->assertJsonPath('data.payment_expires_at', $order->payment_expires_at->toIso8601String());

        $byToken = $this->getJson(route('api.v1.customer.public.order.show', $order->public_token));
        $byToken->assertStatus(200)
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    public function test_public_tracking_not_found(): void
    {
        $this->getJson(route('api.v1.customer.public.order.show', 'NON-EXISTENT'))
            ->assertStatus(404);
    }

    // ── E. Public payment status → paid ──────────────────────────────────────

    public function test_payment_status_marks_paid_when_provider_paid(): void
    {
        $mock = Mockery::mock(QrisService::class);
        $mock->shouldReceive('check')->andReturn([
            'paid'               => true,
            'status'             => 'paid',
            'transaction_status' => 'settlement',
            'raw'                => [],
        ]);
        $this->app->instance(QrisService::class, $mock);

        $order = $this->makeTableOrder();

        $response = $this->getJson(route('api.v1.customer.public.order.payment-status', $order->order_number));

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.order_status', OrderStatus::Confirmed->value);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'payment_status' => PaymentStatus::Paid->value,
            'order_status'   => OrderStatus::Confirmed->value,
            // Table order tetap bill_status=none.
            'bill_status'    => BillStatus::None->value,
        ]);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    // ── F. Expired payment ────────────────────────────────────────────────────

    public function test_payment_status_marks_cancelled_when_expired(): void
    {
        $mock = Mockery::mock(QrisService::class);
        $mock->shouldReceive('cancel')->andReturn(['status' => 'cancelled']);
        $mock->shouldReceive('check')->andReturn([
            'paid'               => false,
            'status'             => 'pending',
            'transaction_status' => 'pending',
            'raw'                => [],
        ]);
        $this->app->instance(QrisService::class, $mock);

        $order = $this->makeTableOrder(expiresAt: now()->subMinute());

        $response = $this->getJson(route('api.v1.customer.public.order.payment-status', $order->order_number));

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_status', PaymentStatus::Cancelled->value)
            ->assertJsonPath('data.order_status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.cancel_reason', 'Payment Timeout');

        // Shape tetap order detail (punya items array).
        $this->assertIsArray($response->json('data.items'));
    }

    public function test_public_tracking_expires_table_order_consistently(): void
    {
        $order = $this->makeTableOrder(expiresAt: now()->subMinute());

        $response = $this->getJson(route('api.v1.customer.public.order.show', $order->order_number));

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_status', PaymentStatus::Cancelled->value)
            ->assertJsonPath('data.order_status', OrderStatus::Cancelled->value);
    }

    // ── Token endpoints must reject table orders ──────────────────────────────

    public function test_table_order_cannot_be_accessed_via_token_endpoints(): void
    {
        $order = $this->makeTableOrder();

        // Walaupun punya public_token, table order tidak valid di jalur open bill.
        $this->getJson(route('api.v1.customer.order.show'), [
            'X-Public-Token' => $order->public_token,
        ])->assertStatus(403);
    }

    // ── Factory helper ────────────────────────────────────────────────────────

    private function makeTableOrder(?\Illuminate\Support\Carbon $expiresAt = null): Order
    {
        return Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'order_type'                   => OrderType::TableOrder,
            'bill_status'                  => BillStatus::None,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Pending,
            'payment_method'               => 'qris',
            'payment_reference'            => 'santap-test',
            'tax_rate_snapshot'            => 11,
            'service_charge_rate_snapshot' => 5,
            'subtotal_amount'              => 20000,
            'total_amount'                 => 23200,
            'opened_at'                    => now(),
            'payment_expires_at'           => $expiresAt ?? now()->addMinutes(5),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
