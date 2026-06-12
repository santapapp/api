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
use App\Services\OrderLifecycleService;
use App\Filament\Resources\OpenBillSessions\OpenBillSessionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OpenBillSessionFixTest extends TestCase
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
     * Skenario 1: markPaid(closeBill: true) pada open bill yang unpaid mengubah status ke paid dan closed.
     */
    public function test_mark_paid_closes_unpaid_bill(): void
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
            'total_amount'                 => 25000,
        ]);

        $order->markPaid(closeBill: true);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::Paid, $fresh->payment_status);
        $this->assertSame(BillStatus::Closed, $fresh->bill_status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertNotNull($fresh->closed_at);
    }

    /**
     * Skenario 2: markPaid(closeBill: true) pada open bill yang sudah paid tetapi bill_status masih open berhasil menutup bill_status menjadi closed.
     */
    public function test_mark_paid_closes_already_paid_open_bill(): void
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
            'payment_status'               => PaymentStatus::Paid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 25000,
            'paid_at'                      => now()->subMinute(),
        ]);

        $order->markPaid(closeBill: true);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::Paid, $fresh->payment_status);
        $this->assertSame(BillStatus::Closed, $fresh->bill_status);
        $this->assertNotNull($fresh->closed_at);
    }

    /**
     * Skenario 3: Pembatalan order yang sudah dibayar/lunas melempar ValidationException.
     */
    public function test_cannot_cancel_already_paid_order(): void
    {
        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Closed,
            'order_status'                 => OrderStatus::Confirmed,
            'payment_status'               => PaymentStatus::Paid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 25000,
            'paid_at'                      => now(),
            'closed_at'                    => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(OrderLifecycleService::class)->cancelOrder($order, $this->cashier, 'Refund/Cancel');
    }

    /**
     * Skenario 3b: Pembatalan order yang sudah dibayar/lunas via API mengembalikan status 422.
     */
    public function test_api_cannot_cancel_already_paid_order(): void
    {
        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Closed,
            'order_status'                 => OrderStatus::Confirmed,
            'payment_status'               => PaymentStatus::Paid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 25000,
            'paid_at'                      => now(),
            'closed_at'                    => now(),
        ]);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.cancel', $order->id), [
                'cancel_reason' => 'Batal saja',
            ]);

        $response->assertStatus(422);
    }

    /**
     * Skenario 4: Sesi QR/token yang sudah closed mengembalikan status 403 di customer endpoint.
     */
    public function test_closed_token_returns_403_in_customer_endpoint(): void
    {
        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Closed,
            'order_status'                 => OrderStatus::Confirmed,
            'payment_status'               => PaymentStatus::Paid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 25000,
            'paid_at'                      => now(),
            'closed_at'                    => now(),
        ]);

        $response = $this->withHeader('X-Public-Token', $order->public_token)
            ->getJson(route('api.v1.customer.order.show'));

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Sesi open bill tidak valid atau sudah berakhir.');
    }

    /**
     * Skenario 5: Customer tidak bisa tambah item ke open bill yang closed/paid.
     */
    public function test_customer_cannot_add_items_to_closed_open_bill(): void
    {
        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Closed,
            'order_status'                 => OrderStatus::Confirmed,
            'payment_status'               => PaymentStatus::Paid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 25000,
            'paid_at'                      => now(),
            'closed_at'                    => now(),
        ]);

        // Kami lewati middleware token menggunakan actingAs/headers/atau test backend langsung untuk menguji mutabilitas item
        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.add-items', $order->id), [
                'items' => [
                    ['menu_id' => $this->product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);
    }

    /**
     * Skenario 6: Open bill paid/closed tidak muncul di daftar active session admin.
     */
    public function test_closed_open_bill_does_not_appear_in_active_session_query(): void
    {
        $activeOrder = Order::create([
            'order_number'                 => 'ORD-ACTIVE',
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
            'total_amount'                 => 25000,
            'opened_at'                    => now(),
        ]);

        $closedOrder = Order::create([
            'order_number'                 => 'ORD-CLOSED',
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->cashier->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Closed,
            'order_status'                 => OrderStatus::Confirmed,
            'payment_status'               => PaymentStatus::Paid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 25000,
            'opened_at'                    => now(),
            'closed_at'                    => now(),
            'paid_at'                      => now(),
        ]);

        $activeQueryIds = OpenBillSessionResource::getEloquentQuery()->pluck('id')->toArray();

        $this->assertContains($activeOrder->id, $activeQueryIds);
        $this->assertNotContains($closedOrder->id, $activeQueryIds);
    }
}
