<?php

declare(strict_types=1);

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
use App\Models\OpenBill;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MvpEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        RateLimiter::clear('login');
    }

    public function test_full_mvp_workflow_end_to_end(): void
    {
        // 1. Owner Login and Master Data Creation
        $owner = User::where('email', 'owner@santap.com')->first();
        $org = Organization::where('slug', 'warung-padang-sekeco')->first();

        $this->actingAs($owner);
        
        // Switch context
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $org->slug]);

        // Owner creates a new Table via API
        $tableResponse = $this->postJson(route('api.v1.dining-tables.store'), [
            'name' => 'Meja VIP',
            'code' => 'TVIP',
            'status' => 'available',
        ], [
            'X-Organization-Id' => $org->id,
        ]);
        $tableResponse->assertStatus(201);
        $tableId = $tableResponse->json('data.id');
        $qrToken = $tableResponse->json('data.qr_token');

        // Owner creates a new Menu
        $category = \App\Models\MenuCategory::where('slug', 'makanan')->first();
        $menuResponse = $this->postJson(route('api.v1.menus.store'), [
            'menu_category_id' => $category->id,
            'name' => 'Gulai Otak',
            'price' => 30000,
            'status' => 'active',
            'sku' => 'M999',
        ], [
            'X-Organization-Id' => $org->id,
        ]);
        $menuResponse->assertStatus(201);
        $menuId = $menuResponse->json('data.id');

        // Logout owner to clear context
        $this->postJson(route('api.v1.auth.logout'));

        // 2. Customer Scans QR and Starts Session
        $sessionResponse = $this->postJson(route('api.v1.customer.sessions.start'), [
            'organization_slug' => $org->slug,
            'table_code' => 'TVIP',
            'qr_token' => $qrToken,
        ]);
        $sessionResponse->assertStatus(200);
        $customerToken = $sessionResponse->json('session_token');
        $this->assertNotEmpty($customerToken);

        // 3. Customer Views Menu
        $menuViewResponse = $this->getJson(route('api.v1.customer.menu.index'), [
            'X-Customer-Session' => $customerToken,
        ]);
        $menuViewResponse->assertStatus(200);

        // 4. Customer Places Order
        $orderResponse = $this->postJson(route('api.v1.customer.orders.store'), [
            'items' => [
                [
                    'menu_id' => $menuId,
                    'quantity' => 2,
                    'notes' => 'Kuah banyak',
                ]
            ]
        ], [
            'X-Customer-Session' => $customerToken,
        ]);
        $orderResponse->assertStatus(201);

        $orderItem = OrderItem::first();
        $this->assertEquals(60000, $orderResponse->json('data.total_amount'));

        // 5. Kitchen Logs In and Prepares Order
        $kitchen = User::where('email', 'kitchen@santap.com')->first();
        $this->actingAs($kitchen);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $org->slug]);

        // Kitchen views queue
        $kitchenQueue = $this->getJson(route('api.v1.kitchen.orders.index'), [
            'X-Organization-Id' => $org->id,
        ]);
        $kitchenQueue->assertStatus(200);

        // Kitchen cooks order item
        $cookResponse = $this->patchJson(route('api.v1.kitchen.order-items.status', $orderItem->id), [
            'status' => 'cooking',
        ], [
            'X-Organization-Id' => $org->id,
        ]);
        $cookResponse->assertStatus(200);

        // Kitchen completes cooking
        $readyResponse = $this->patchJson(route('api.v1.kitchen.order-items.status', $orderItem->id), [
            'status' => 'ready',
        ], [
            'X-Organization-Id' => $org->id,
        ]);
        $readyResponse->assertStatus(200);

        // Logout kitchen
        $this->postJson(route('api.v1.auth.logout'));

        // 6. Cashier Initiates QRIS Payment
        $cashier = User::where('email', 'cashier@santap.com')->first();
        $this->actingAs($cashier);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $org->slug]);

        $bill = OpenBill::first();

        // Mock external QRIS creation API
        Http::fake([
            'https://qris.sekeco.id/create' => Http::response([
                'ok' => true,
                'message' => 'payment dibuat',
                'data' => [
                    'status_code' => '201',
                    'status_message' => 'Qris transaction is created',
                    'transaction_id' => 'tx-e2e-111',
                    'order_id' => 'order-e2e-222',
                    'gross_amount' => '60000.00',
                    'qr_string' => 'e2e-qr-code-string',
                    'expiry_time' => '2026-05-23 12:00:00',
                ],
            ], 200),
        ]);

        $payResponse = $this->postJson(route('api.v1.payments.store'), [
            'open_bill_id' => $bill->id,
            'method' => 'qris',
        ], [
            'X-Organization-Id' => $org->id,
        ]);
        $payResponse->assertStatus(201);
        $paymentId = $payResponse->json('data.id');

        // Logout cashier
        $this->postJson(route('api.v1.auth.logout'));

        // 7. Webhook Settles Payment
        $webhookResponse = $this->postJson(route('api.v1.payments.webhook'), [
            'order_id' => 'order-e2e-222',
            'transaction_status' => 'settlement',
            'gross_amount' => '60000.00',
            'transaction_id' => 'tx-e2e-111',
        ]);
        $webhookResponse->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'paid',
            'amount' => 60000,
        ]);

        // 8. Cashier Closes Bill
        $this->actingAs($cashier);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $org->slug]);

        $closeResponse = $this->postJson(route('api.v1.open-bills.close', $bill->id), [], [
            'X-Organization-Id' => $org->id,
        ]);
        $closeResponse->assertStatus(200);

        // Verify session and table status
        $this->assertDatabaseHas('open_bills', [
            'id' => $bill->id,
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('dining_tables', [
            'id' => $tableId,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('customer_sessions', [
            'session_token' => $customerToken,
            'status' => 'closed',
        ]);

        // 9. Verify Spatie Activity Log Audit Trail
        // We logged Menu creation, Order status changes, Payment registration/settlement, and OpenBill closure.
        $this->assertTrue(Activity::where('subject_type', Menu::class)->exists());
        $this->assertTrue(Activity::where('subject_type', Order::class)->exists());
        $this->assertTrue(Activity::where('subject_type', Payment::class)->exists());
        $this->assertTrue(Activity::where('subject_type', OpenBill::class)->exists());
    }

    public function test_auth_login_rate_limiting(): void
    {
        // Try to login 6 times in a row, the 6th should fail with 429 Too Many Requests
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson(route('api.v1.auth.login'), [
                'email' => 'owner@santap.com',
                'password' => 'wrong-password',
            ]);
            $response->assertStatus(401);
        }

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'owner@santap.com',
            'password' => 'wrong-password',
        ]);
        $response->assertStatus(429);
    }
}
