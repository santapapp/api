<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\BillStatus;
use App\Enums\MenuType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Events\OpenBillRepeatOrderCreated;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpenBillBroadcastTest extends TestCase
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
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'is_active' => true,
        ]);

        $this->table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name' => 'Table A1',
            'qr_token' => Str::random(32),
            'is_active' => true,
        ]);

        $this->publicToken = Str::random(32);

        $this->order = Order::create([
            'order_number' => 'ORD-123456',
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

    private function makeProduct(string $name = 'Ramen', float $price = 35000): Menu
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

    public function test_adding_items_to_open_bill_dispatches_repeat_order_created_event(): void
    {
        Event::fake([OpenBillRepeatOrderCreated::class]);

        $product = $this->makeProduct('Nasi Goreng Special', 25000);

        $response = $this->postJson(
            route('api.v1.customer.order.add-items'),
            [
                'items' => [
                    ['menu_id' => $product->id, 'quantity' => 2]
                ]
            ],
            ['X-Public-Token' => $this->publicToken]
        );

        $response->assertStatus(200);

        Event::assertDispatched(OpenBillRepeatOrderCreated::class, function (OpenBillRepeatOrderCreated $event) use ($product) {
            $this->assertEquals($this->order->id, $event->billId);
            $this->assertEquals($this->org->id, $event->organizationId);
            $this->assertEquals($this->table->id, $event->tableId);
            $this->assertEquals($this->order->order_number, $event->orderNumber);
            $this->assertNotEmpty($event->batch);
            
            // Check that the items in the event payload map to the added product
            $this->assertCount(1, $event->items);
            $this->assertEquals($product->id, $event->items[0]['menu_id']);
            $this->assertEquals(2, $event->items[0]['quantity']);
            $this->assertEquals(50000.0, $event->items[0]['subtotal']);

            // Event channel verification
            $channels = collect($event->broadcastOn())->map(fn($c) => (string)$c)->all();
            $this->assertContains("private-open-bill.{$this->order->id}", $channels);
            $this->assertContains("private-organization.{$this->org->id}", $channels);

            // Broadcast alias verification
            $this->assertEquals('repeat-order-created', $event->broadcastAs());

            return true;
        });
    }
}
