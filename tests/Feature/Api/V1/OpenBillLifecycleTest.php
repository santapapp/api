<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BillStatus;
use App\Enums\ItemStatus;
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
use App\Services\QrisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class OpenBillLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private DiningTable $table;
    private User $cashier;
    private Menu $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'      => 'Lifecycle Resto',
            'slug'      => 'lifecycle-resto',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create();

        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->cashier->id,
            'role'            => 'cashier',
        ]);

        $this->table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name'            => 'Meja 1',
            'code'            => 'T1',
            'qr_token'        => Str::random(32),
            'is_active'       => true,
        ]);

        $this->product = Menu::create([
            'organization_id' => $this->org->id,
            'type'            => MenuType::Product,
            'name'            => 'Ayam Bakar',
            'price'           => 25000,
            'is_available'    => true,
            'sort_order'      => 1,
        ]);
    }

    private function actingAsCashier(): self
    {
        return $this->actingAs($this->cashier)
            ->withHeader('X-Org-ID', (string) $this->org->id);
    }

    /**
     * Skenario A: Create open bill.
     */
    public function test_create_open_bill(): void
    {
        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), [
                'order_type'      => OrderType::OpenBill->value,
                'dining_table_id' => $this->table->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.order_type', OrderType::OpenBill->value);
        $response->assertJsonPath('data.bill_status', BillStatus::Open->value);
        $response->assertJsonPath('data.order_status', OrderStatus::Pending->value);
        $response->assertJsonPath('data.payment_status', PaymentStatus::Unpaid->value);

        $order = Order::findOrFail($response->json('data.id'));
        $this->assertNotEmpty($order->public_token);
        $this->assertNull($order->closed_at);
    }

    /**
     * Skenario B: Cancel open bill.
     */
    public function test_cancel_open_bill(): void
    {
        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Open,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'subtotal_amount'              => 0,
            'discount_amount'              => 0,
            'tax_amount'                   => 0,
            'service_charge_amount'        => 0,
            'total_amount'                 => 0,
            'payment_amount'               => 0,
            'change_amount'                => 0,
            'opened_at'                    => now(),
        ]);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.cancel', $order->id), [
                'cancel_reason' => 'Pelanggan membatalkan pesanan',
            ]);

        $response->assertOk();

        $freshOrder = $order->fresh();
        $this->assertSame(OrderStatus::Cancelled, $freshOrder->order_status);
        $this->assertSame(PaymentStatus::Cancelled, $freshOrder->payment_status);
        $this->assertSame(BillStatus::Closed, $freshOrder->bill_status);
        $this->assertNotNull($freshOrder->cancelled_at);
        $this->assertNotNull($freshOrder->closed_at);
    }

    /**
     * Skenario C: Token cancelled tidak valid.
     */
    public function test_cancelled_token_returns_unauthorized_in_middleware(): void
    {
        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Closed,
            'order_status'                 => OrderStatus::Cancelled,
            'payment_status'               => PaymentStatus::Cancelled,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'subtotal_amount'              => 0,
            'discount_amount'              => 0,
            'tax_amount'                   => 0,
            'service_charge_amount'        => 0,
            'total_amount'                 => 0,
            'payment_amount'               => 0,
            'change_amount'                => 0,
            'opened_at'                    => now(),
            'closed_at'                    => now(),
            'cancelled_at'                 => now(),
        ]);

        $response = $this->withHeader('X-Public-Token', $order->public_token)
            ->getJson(route('api.v1.customer.order.show'));

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Sesi open bill tidak valid atau sudah berakhir.');
    }

    /**
     * Skenario D: Tidak bisa add item setelah cancel.
     */
    public function test_cannot_add_item_to_cancelled_open_bill(): void
    {
        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Closed,
            'order_status'                 => OrderStatus::Cancelled,
            'payment_status'               => PaymentStatus::Cancelled,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'subtotal_amount'              => 0,
            'discount_amount'              => 0,
            'tax_amount'                   => 0,
            'service_charge_amount'        => 0,
            'total_amount'                 => 0,
            'payment_amount'               => 0,
            'change_amount'                => 0,
            'opened_at'                    => now(),
            'closed_at'                    => now(),
            'cancelled_at'                 => now(),
        ]);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.add-items', $order->id), [
                'items' => [
                    ['menu_id' => $this->product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);
    }

    /**
     * Skenario E: Cancel QRIS attempt tidak menutup open bill.
     */
    public function test_cancel_qris_payment_attempt_does_not_cancel_open_bill(): void
    {
        // Setup mock QRIS Service
        $mock = Mockery::mock(QrisService::class);
        $mock->shouldReceive('create')
            ->once()
            ->andReturn([
                'data' => [
                    'actions'            => [['url' => 'https://qris.example/qr.png']],
                    'qr_string'          => 'QR-STRING',
                    'transaction_status' => 'pending',
                ],
            ]);
        $mock->shouldReceive('cancel')->once()->andReturn(['status' => 'cancelled']);
        $this->app->instance(QrisService::class, $mock);

        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Open,
            'order_status'                 => OrderStatus::Confirmed,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'subtotal_amount'              => 25000,
            'discount_amount'              => 0,
            'tax_amount'                   => 0,
            'service_charge_amount'        => 0,
            'total_amount'                 => 25000,
            'payment_amount'               => 0,
            'change_amount'                => 0,
            'opened_at'                    => now(),
        ]);

        OrderItem::create([
            'order_id'    => $order->id,
            'menu_id'     => $this->product->id,
            'item_type'   => 'product',
            'name'        => $this->product->name,
            'base_price'  => 25000,
            'unit_price'  => 25000,
            'price'       => 25000,
            'quantity'    => 1,
            'subtotal'    => 25000,
            'item_status' => ItemStatus::Pending,
        ]);

        // Buat QRIS
        $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id))
            ->assertOk();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);

        // Batalkan QRIS attempt
        $this->actingAsCashier()
            ->deleteJson(route('api.v1.cashier.orders.qris-cancel', $order->id))
            ->assertOk();

        // Verifikasi open bill tetap open
        $freshOrder = $order->fresh();
        $this->assertSame(OrderStatus::Confirmed, $freshOrder->order_status);
        $this->assertSame(BillStatus::Open, $freshOrder->bill_status);
        $this->assertSame(PaymentStatus::Cancelled, $freshOrder->payment_status);
    }

    /**
     * Skenario F: Paid open bill menutup bill.
     */
    public function test_paid_open_bill_via_cash_closes_bill(): void
    {
        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Open,
            'order_status'                 => OrderStatus::Confirmed,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'subtotal_amount'              => 25000,
            'discount_amount'              => 0,
            'tax_amount'                   => 0,
            'service_charge_amount'        => 0,
            'total_amount'                 => 25000,
            'payment_amount'               => 0,
            'change_amount'                => 0,
            'opened_at'                    => now(),
        ]);

        OrderItem::create([
            'order_id'    => $order->id,
            'menu_id'     => $this->product->id,
            'item_type'   => 'product',
            'name'        => $this->product->name,
            'base_price'  => 25000,
            'unit_price'  => 25000,
            'price'       => 25000,
            'quantity'    => 1,
            'subtotal'    => 25000,
            'item_status' => ItemStatus::Pending,
        ]);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-cash', $order->id), [
                'amount_received' => 30000,
            ]);

        $response->assertOk();

        $freshOrder = $order->fresh();
        $this->assertSame(PaymentStatus::Paid, $freshOrder->payment_status);
        $this->assertSame(BillStatus::Closed, $freshOrder->bill_status);
        $this->assertNotNull($freshOrder->closed_at);
        $this->assertNotNull($freshOrder->paid_at);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
