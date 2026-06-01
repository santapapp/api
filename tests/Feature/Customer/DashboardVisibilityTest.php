<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Memastikan table order yang masih pending/unpaid TIDAK bocor ke dashboard
 * cashier maupun antrian kitchen, dan baru muncul setelah paid/confirmed.
 */
class DashboardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;
    private DiningTable $table;
    private Menu $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->org   = Organization::create([
            'name'      => 'Resto Visibility',
            'slug'      => 'resto-visibility',
            'is_active' => true,
        ]);
        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->owner->id,
            'role'            => 'owner',
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
            'name'            => 'Nasi Goreng',
            'price'           => 20000,
            'is_available'    => true,
            'sort_order'      => 1,
        ]);
    }

    private function makeTableOrder(
        PaymentStatus $payment,
        OrderStatus $status,
        BillStatus $bill = BillStatus::None,
    ): Order {
        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'order_type'                   => OrderType::TableOrder,
            'bill_status'                  => $bill,
            'order_status'                 => $status,
            'payment_status'               => $payment,
            'payment_method'               => 'qris',
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'subtotal_amount'              => 20000,
            'total_amount'                 => 20000,
            'opened_at'                    => now(),
        ]);

        OrderItem::create([
            'order_id'    => $order->id,
            'menu_id'     => $this->product->id,
            'item_type'   => 'product',
            'name'        => $this->product->name,
            'base_price'  => 20000,
            'unit_price'  => 20000,
            'price'       => 20000,
            'quantity'    => 1,
            'subtotal'    => 20000,
            'item_status' => ItemStatus::Pending->value,
        ]);

        return $order;
    }

    public function test_pending_unpaid_table_order_is_hidden_from_cashier(): void
    {
        $this->makeTableOrder(PaymentStatus::Pending, OrderStatus::Pending);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Org-ID', (string) $this->org->id)
            ->getJson(route('api.v1.cashier.orders.index'));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_paid_confirmed_table_order_is_visible_to_cashier(): void
    {
        $this->makeTableOrder(PaymentStatus::Paid, OrderStatus::Confirmed);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Org-ID', (string) $this->org->id)
            ->getJson(route('api.v1.cashier.orders.index'));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_pending_unpaid_table_order_is_hidden_from_kitchen(): void
    {
        $this->makeTableOrder(PaymentStatus::Pending, OrderStatus::Pending);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Org-ID', (string) $this->org->id)
            ->getJson(route('api.v1.kitchen.orders.index'));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_confirmed_table_order_is_visible_to_kitchen(): void
    {
        $this->makeTableOrder(PaymentStatus::Paid, OrderStatus::Confirmed);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Org-ID', (string) $this->org->id)
            ->getJson(route('api.v1.kitchen.orders.index'));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
