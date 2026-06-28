<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\BillStatus;
use App\Enums\MenuType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Events\OrderPlaced;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderNotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private DiningTable $table;
    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->org = Organization::create([
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'is_active' => true,
        ]);
        $this->user->organizations()->attach($this->org->id, ['role' => 'cashier']);

        $this->table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name' => 'Meja B5',
            'qr_token' => Str::random(32),
            'is_active' => true,
        ]);

        $this->menu = Menu::create([
            'organization_id' => $this->org->id,
            'type' => MenuType::Product,
            'name' => 'Double Pepperoni',
            'price' => 75000,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_customer_checkout_triggers_order_placed_broadcast(): void
    {
        Event::fake([OrderPlaced::class]);

        $response = $this->postJson(
            route('api.v1.customer.order.create'),
            [
                'qr_token' => $this->table->qr_token,
                'items' => [
                    ['menu_id' => $this->menu->id, 'quantity' => 2]
                ]
            ]
        );

        $response->assertStatus(201);
        $orderId = $response->json('data.order_id');

        Event::assertDispatched(OrderPlaced::class, function (OrderPlaced $event) use ($orderId) {
            $this->assertEquals($orderId, $event->orderId);
            $this->assertEquals($this->org->id, $event->organizationId);
            $this->assertEquals($this->table->id, $event->tableId);
            $this->assertEquals('Meja B5', $event->tableName);
            $this->assertEquals('table_order', $event->orderType);
            $this->assertEquals(150000.0, $event->totalAmount); // 75000 * 2
            $this->assertEquals(1, $event->itemsCount);

            // Channel check
            $channels = collect($event->broadcastOn())->map(fn($c) => (string)$c)->all();
            $this->assertContains("private-organization.{$this->org->id}", $channels);
            $this->assertEquals('order-placed', $event->broadcastAs());

            return true;
        });
    }

    public function test_paying_order_triggers_order_paid_broadcast(): void
    {
        Event::fake([OrderPaid::class]);

        // Create an existing open bill order
        $order = Order::create([
            'order_number' => 'ORD-987654',
            'public_token' => Str::random(32),
            'organization_id' => $this->org->id,
            'dining_table_id' => $this->table->id,
            'order_type' => OrderType::OpenBill,
            'bill_status' => BillStatus::Open,
            'order_status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'tax_rate_snapshot' => 0,
            'service_charge_rate_snapshot' => 0,
            'subtotal_amount' => 75000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'total_amount' => 75000,
            'payment_amount' => 0,
            'change_amount' => 0,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(
                route('api.v1.cashier.orders.pay-cash', ['id' => $order->id]),
                ['amount_received' => 100000],
                ['X-Org-ID' => (string) $this->org->id]
            );

        $response->assertStatus(200);

        Event::assertDispatched(OrderPaid::class, function (OrderPaid $event) use ($order) {
            $this->assertEquals($order->id, $event->orderId);
            $this->assertEquals($this->org->id, $event->organizationId);
            $this->assertEquals('Meja B5', $event->tableName);
            $this->assertEquals('cash', $event->paymentMethod);
            $this->assertEquals(75000.0, $event->totalAmount);

            // Channel check
            $channels = collect($event->broadcastOn())->map(fn($c) => (string)$c)->all();
            $this->assertContains("private-organization.{$this->org->id}", $channels);
            $this->assertContains("private-open-bill.{$order->id}", $channels);
            $this->assertEquals('order-paid', $event->broadcastAs());

            return true;
        });
    }
}
