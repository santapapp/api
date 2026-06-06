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

class OpenBillQrisGuardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $otherOrg;
    private DiningTable $table;
    private User $cashier;
    private User $kitchen;
    private Menu $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Guard Resto',
            'slug' => 'guard-resto',
            'is_active' => true,
        ]);
        $this->otherOrg = Organization::create([
            'name' => 'Other Resto',
            'slug' => 'other-resto',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create();
        $this->kitchen = User::factory()->create();

        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->cashier->id,
            'role' => 'cashier',
        ]);
        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->kitchen->id,
            'role' => 'kitchen',
        ]);

        $this->table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name' => 'Meja 1',
            'code' => 'T1',
            'qr_token' => Str::random(32),
            'is_active' => true,
        ]);

        $this->product = $this->makeProduct($this->org, 'Nasi Goreng', 20000);
    }

    public function test_cannot_create_qris_when_total_is_zero(): void
    {
        $order = $this->makeOpenBill(withItem: false);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
            'payment_status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_cannot_double_create_qris_for_same_order_while_pending(): void
    {
        $this->mockQrisCreate(times: 1);
        $order = $this->makeOpenBill();

        $first = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id));
        $first->assertOk();

        $second = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id));
        $second->assertOk()
            ->assertJsonPath('message', 'Order ini masih memiliki QRIS pending aktif.');

        $this->assertSame(
            $first->json('data.payment_reference'),
            $second->json('data.payment_reference'),
        );
    }

    public function test_can_create_qris_for_different_order_while_another_order_is_pending(): void
    {
        $this->mockQrisCreate(times: 2);
        $orderA = $this->makeOpenBill();
        $orderB = $this->makeOpenBill();

        $first = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $orderA->id));
        $second = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $orderB->id));

        $first->assertOk();
        $second->assertOk();
        $this->assertNotSame(
            $first->json('data.payment_reference'),
            $second->json('data.payment_reference'),
        );
    }

    public function test_can_regenerate_qris_for_same_order_after_cancelled(): void
    {
        $this->mockQrisCreate(times: 2, withCancel: true);
        $order = $this->makeOpenBill();

        $first = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id));
        $first->assertOk();

        $this->actingAsCashier()
            ->deleteJson(route('api.v1.cashier.orders.qris-cancel', $order->id))
            ->assertOk();

        $second = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id));
        $second->assertOk();

        $fresh = $order->fresh();
        $attempts = $fresh->metadata['qris_attempts'];

        $this->assertCount(1, $attempts);
        $this->assertSame('cancelled', $attempts[0]['status']);
        $this->assertNotSame(
            $first->json('data.payment_reference'),
            $second->json('data.payment_reference'),
        );
    }

    public function test_can_regenerate_qris_for_same_order_after_expired(): void
    {
        $this->mockQrisCreate(times: 2, withPendingCheck: true);
        $order = $this->makeOpenBill();

        $first = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id));
        $first->assertOk();

        $order->update(['payment_expires_at' => now()->subMinute()]);

        $second = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id));
        $second->assertOk();

        $fresh = $order->fresh();
        $attempts = $fresh->metadata['qris_attempts'];

        $this->assertCount(1, $attempts);
        $this->assertSame('expired', $attempts[0]['status']);
        $this->assertSame(PaymentStatus::Pending, $fresh->payment_status);
        $this->assertNotSame(
            $first->json('data.payment_reference'),
            $second->json('data.payment_reference'),
        );
    }

    public function test_cannot_edit_item_when_qris_pending_or_paid(): void
    {
        $this->mockQrisCreate(times: 1);
        $order = $this->makeOpenBill();
        $item = $order->items()->first();

        $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id))
            ->assertOk();

        $this->actingAsCashier()
            ->patchJson(route('api.v1.cashier.orders.update-item', [$order->id, $item->id]), [
                'quantity' => 2,
            ])
            ->assertStatus(422);

        $order->fresh()->markPaid(closeBill: true);

        $this->actingAsCashier()
            ->patchJson(route('api.v1.cashier.orders.update-item', [$order->id, $item->id]), [
                'quantity' => 2,
            ])
            ->assertStatus(422);
    }

    public function test_menu_and_options_must_belong_to_same_organization(): void
    {
        $order = $this->makeOpenBill();
        $otherProduct = $this->makeProduct($this->otherOrg, 'Produk Lain', 10000);

        $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.add-items', $order->id), [
                'items' => [
                    ['menu_id' => $otherProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422);

        $group = $this->makeGroup($this->product);
        $otherGroup = $this->makeGroup($otherProduct);
        $otherOption = $this->makeOption($otherGroup);

        $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.add-items', $order->id), [
                'items' => [[
                    'menu_id' => $this->product->id,
                    'quantity' => 1,
                    'selected_options' => [
                        ['group_id' => $group->id, 'option_id' => $otherOption->id],
                    ],
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_kitchen_role_cannot_mutate_open_bill_item_or_payment(): void
    {
        $order = $this->makeOpenBill();

        $this->actingAsKitchen()
            ->postJson(route('api.v1.cashier.orders.add-items', $order->id), [
                'items' => [
                    ['menu_id' => $this->product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(403);

        $this->actingAsKitchen()
            ->postJson(route('api.v1.cashier.orders.pay-qris', $order->id))
            ->assertStatus(403);
    }

    private function actingAsCashier(): self
    {
        return $this->actingAs($this->cashier)
            ->withHeader('X-Org-ID', (string) $this->org->id);
    }

    private function actingAsKitchen(): self
    {
        return $this->actingAs($this->kitchen)
            ->withHeader('X-Org-ID', (string) $this->org->id);
    }

    private function makeOpenBill(bool $withItem = true): Order
    {
        $order = Order::create([
            'order_number' => Order::generateOrderNumber($this->org->id),
            'public_token' => Str::random(32),
            'organization_id' => $this->org->id,
            'dining_table_id' => $this->table->id,
            'created_by' => $this->cashier->id,
            'order_type' => OrderType::OpenBill,
            'bill_status' => BillStatus::Open,
            'order_status' => OrderStatus::Confirmed,
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

        if ($withItem) {
            OrderItem::create([
                'order_id' => $order->id,
                'menu_id' => $this->product->id,
                'item_type' => 'product',
                'name' => $this->product->name,
                'base_price' => 20000,
                'variant_total' => 0,
                'unit_price' => 20000,
                'price' => 20000,
                'quantity' => 1,
                'subtotal' => 20000,
                'item_status' => ItemStatus::Pending,
            ]);

            $order->recalculate();
        }

        return $order->fresh();
    }

    private function makeProduct(Organization $organization, string $name, float $price): Menu
    {
        return Menu::create([
            'organization_id' => $organization->id,
            'type' => MenuType::Product,
            'name' => $name,
            'price' => $price,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function makeGroup(Menu $product): Menu
    {
        return Menu::create([
            'organization_id' => $product->organization_id,
            'parent_id' => $product->id,
            'type' => MenuType::AddonGroup,
            'name' => 'Addon',
            'price' => 0,
            'is_available' => true,
            'min_select' => 0,
            'max_select' => 2,
            'sort_order' => 1,
        ]);
    }

    private function makeOption(Menu $group): Menu
    {
        return Menu::create([
            'organization_id' => $group->organization_id,
            'parent_id' => $group->id,
            'type' => MenuType::Addon,
            'name' => 'Telur',
            'price' => 5000,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function mockQrisCreate(int $times, bool $withCancel = false, bool $withPendingCheck = false): void
    {
        $mock = Mockery::mock(QrisService::class);
        $mock->shouldReceive('create')
            ->times($times)
            ->andReturn([
                'data' => [
                    'actions' => [['url' => 'https://qris.example/qr.png']],
                    'qr_string' => 'QR-STRING',
                    'transaction_status' => 'pending',
                ],
            ]);

        if ($withCancel) {
            $mock->shouldReceive('cancel')->once()->andReturn(['status' => 'cancelled']);
        }

        if ($withPendingCheck) {
            $mock->shouldReceive('check')->once()->andReturn([
                'paid' => false,
                'status' => 'pending',
                'transaction_status' => 'pending',
                'raw' => [],
            ]);
        }

        $this->app->instance(QrisService::class, $mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
