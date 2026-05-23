<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BillStatus;
use App\Enums\CustomerSessionStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\OpenBill;
use App\Models\Organization;
use App\Models\TableQrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private User $kitchen;
    private Organization $organization;
    private DiningTable $table;
    private Menu $menu;
    private CustomerSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->cashier = User::factory()->create();
        $this->kitchen = User::factory()->create();

        $this->organization = Organization::create([
            'name' => 'Resto Berkah',
            'slug' => 'resto-berkah',
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        
        $this->organization->users()->attach($this->owner->id, ['role_name' => 'owner']);
        $this->owner->assignRole('owner');

        $this->organization->users()->attach($this->cashier->id, ['role_name' => 'cashier']);
        $this->cashier->assignRole('cashier');

        $this->organization->users()->attach($this->kitchen->id, ['role_name' => 'kitchen']);
        $this->kitchen->assignRole('kitchen');

        $this->table = DiningTable::create([
            'organization_id' => $this->organization->id,
            'name' => 'Meja 1',
            'code' => 'T1',
            'status' => TableStatus::Occupied,
        ]);

        $category = MenuCategory::create([
            'organization_id' => $this->organization->id,
            'name' => 'Makanan',
            'slug' => 'makanan',
        ]);

        $this->menu = Menu::create([
            'organization_id' => $this->organization->id,
            'menu_category_id' => $category->id,
            'name' => 'Nasi Goreng',
            'slug' => 'nasi-goreng',
            'price' => 20000,
            'status' => MenuStatus::Active,
        ]);

        $bill = OpenBill::create([
            'organization_id' => $this->organization->id,
            'dining_table_id' => $this->table->id,
            'bill_number' => 'BILL-123',
            'status' => BillStatus::Open,
        ]);

        $this->session = CustomerSession::create([
            'organization_id' => $this->organization->id,
            'dining_table_id' => $this->table->id,
            'open_bill_id' => $bill->id,
            'session_token' => Str::random(40),
            'status' => CustomerSessionStatus::Active,
            'started_at' => now(),
            'expires_at' => now()->addHours(4),
        ]);
    }

    public function test_customer_can_create_order(): void
    {
        $response = $this->postJson(route('api.v1.customer.orders.store'), [
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 2,
                    'notes' => 'Pedas',
                ],
            ]
        ], [
            'X-Customer-Session' => $this->session->session_token,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'organization_id' => $this->organization->id,
            'status' => OrderStatus::Pending->value,
            'total_amount' => 40000, // 2 x 20000
        ]);
        $this->assertDatabaseHas('order_items', [
            'menu_id' => $this->menu->id,
            'quantity' => 2,
            'menu_price_snapshot' => 20000,
            'subtotal_amount' => 40000,
        ]);
    }

    public function test_kitchen_can_update_order_item_status(): void
    {
        // Customer creates order
        $this->postJson(route('api.v1.customer.orders.store'), [
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 1,
                ],
            ]
        ], [
            'X-Customer-Session' => $this->session->session_token,
        ]);

        $orderItem = \App\Models\OrderItem::first();

        // Kitchen login & switch context
        $this->actingAs($this->kitchen);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->organization->slug]);

        // Kitchen changes status to cooking
        $response = $this->patchJson(route('api.v1.kitchen.order-items.status', $orderItem->id), [
            'status' => OrderItemStatus::Cooking->value,
        ], [
            'X-Organization-Id' => $this->organization->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'status' => OrderItemStatus::Cooking->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $orderItem->order_id,
            'status' => OrderStatus::Cooking->value,
        ]);
    }

    public function test_cashier_can_pay_and_close_bill(): void
    {
        // Customer creates order
        $this->postJson(route('api.v1.customer.orders.store'), [
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 1,
                ],
            ]
        ], [
            'X-Customer-Session' => $this->session->session_token,
        ]);

        // Cashier login
        $this->actingAs($this->cashier);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->organization->slug]);

        // Pay Bill
        $bill = $this->session->fresh()->bill;
        $response = $this->postJson(route('api.v1.payments.store'), [
            'open_bill_id' => $bill->id,
            'method' => 'cash',
            'paid_amount' => 20000, // exact amount
        ], [
            'X-Organization-Id' => $this->organization->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payments', [
            'open_bill_id' => $bill->id,
            'status' => PaymentStatus::Paid->value,
            'amount' => 20000,
        ]);

        // Close Bill
        $response = $this->postJson(route('api.v1.open-bills.close', $bill->id), [], [
            'X-Organization-Id' => $this->organization->id,
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('open_bills', [
            'id' => $bill->id,
            'status' => BillStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('customer_sessions', [
            'id' => $this->session->id,
            'status' => CustomerSessionStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('dining_tables', [
            'id' => $this->table->id,
            'status' => TableStatus::Available->value,
        ]);
    }
}
