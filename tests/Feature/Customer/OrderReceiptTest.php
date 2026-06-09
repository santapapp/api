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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private DiningTable  $table;
    private Menu         $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'                   => 'Warung Padang Demo',
            'slug'                   => 'warung-padang-demo',
            'is_active'              => true,
            'tax_enabled'            => true,
            'tax_rate'               => 11.00,
            'service_charge_enabled' => true,
            'service_charge_rate'    => 5.00,
        ]);

        $this->table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name'            => 'Meja 5',
            'code'            => 'M5',
            'qr_token'        => Str::random(32),
            'is_active'       => true,
        ]);

        $this->product = Menu::create([
            'organization_id' => $this->org->id,
            'type'            => MenuType::Product,
            'name'            => 'Rendang Sapi',
            'price'           => 30000,
            'is_available'    => true,
            'sort_order'      => 1,
        ]);
    }

    /**
     * Helper to build a test order.
     */
    private function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'order_type'                   => OrderType::TableOrder,
            'bill_status'                  => BillStatus::None,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Pending,
            'payment_method'               => 'qris',
            'payment_reference'            => 'santap-test-ref',
            'tax_rate_snapshot'            => 11.00,
            'service_charge_rate_snapshot' => 5.00,
            'subtotal_amount'              => 30000,
            'total_amount'                 => 34800,
            'opened_at'                    => now(),
            'payment_expires_at'           => now()->addMinutes(15),
            'metadata'                     => [
                'sekeco_raw_response' => 'sensitive_token_here',
                'qris_active' => true,
            ]
        ], $attributes));
    }

    public function test_download_receipt_success_when_order_is_paid(): void
    {
        $order = $this->createOrder([
            'payment_status' => PaymentStatus::Paid,
            'order_status'   => OrderStatus::Confirmed,
            'paid_at'        => now(),
        ]);

        // Tambah item ke order agar printout memiliki item
        $order->items()->create([
            'menu_id' => $this->product->id,
            'item_type' => MenuType::Product->value,
            'name' => 'Rendang Sapi',
            'base_price' => 30000,
            'variant_total' => 0,
            'unit_price' => 30000,
            'price' => 30000,
            'quantity' => 1,
            'subtotal' => 30000,
            'item_status' => 'pending',
        ]);

        $response = $this->getJson(route('api.v1.customer.orders.receipt.download', $order->order_number));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // Bisa juga dicari via public token
        $responseToken = $this->getJson(route('api.v1.customer.orders.receipt.download', $order->public_token));
        $responseToken->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_download_receipt_fails_when_order_is_pending_or_unpaid(): void
    {
        $order = $this->createOrder([
            'payment_status' => PaymentStatus::Pending,
        ]);

        $response = $this->getJson(route('api.v1.customer.orders.receipt.download', $order->order_number));

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Struk pembayaran hanya dapat diunduh untuk pesanan yang sudah lunas.');
    }

    public function test_download_receipt_fails_when_order_is_cancelled(): void
    {
        $order = $this->createOrder([
            'payment_status' => PaymentStatus::Cancelled,
            'order_status'   => OrderStatus::Cancelled,
        ]);

        $response = $this->getJson(route('api.v1.customer.orders.receipt.download', $order->order_number));

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Struk pembayaran hanya dapat diunduh untuk pesanan yang sudah lunas.');
    }

    public function test_download_receipt_returns_404_when_order_does_not_exist(): void
    {
        $response = $this->getJson(route('api.v1.customer.orders.receipt.download', 'ORD-NON-EXISTENT'));

        $response->assertStatus(404);
    }
}
