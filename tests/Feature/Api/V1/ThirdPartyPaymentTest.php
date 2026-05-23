<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BillStatus;
use App\Enums\CustomerSessionStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Events\PaymentPaid;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\OpenBill;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ThirdPartyPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Organization $organization;
    private DiningTable $table;
    private OpenBill $bill;
    private CustomerSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->cashier = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Resto Berkah',
            'slug' => 'resto-berkah',
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->organization->users()->attach($this->cashier->id, ['role_name' => 'cashier']);
        $this->cashier->assignRole('cashier');

        $this->table = DiningTable::create([
            'organization_id' => $this->organization->id,
            'name' => 'Meja 1',
            'code' => 'T1',
            'status' => TableStatus::Occupied,
        ]);

        $this->bill = OpenBill::create([
            'organization_id' => $this->organization->id,
            'dining_table_id' => $this->table->id,
            'bill_number' => 'BILL-123',
            'status' => BillStatus::Open,
            'total_amount' => 15000,
        ]);

        $this->session = CustomerSession::create([
            'organization_id' => $this->organization->id,
            'dining_table_id' => $this->table->id,
            'open_bill_id' => $this->bill->id,
            'session_token' => Str::random(40),
            'status' => CustomerSessionStatus::Active,
            'started_at' => now(),
            'expires_at' => now()->addHours(4),
        ]);
    }

    public function test_cashier_can_create_qris_payment(): void
    {
        Http::fake([
            'https://qris.sekeco.id/create' => Http::response([
                'ok' => true,
                'message' => 'payment dibuat',
                'data' => [
                    'status_code' => '201',
                    'status_message' => 'Qris transaction is created',
                    'transaction_id' => 'mock-tx-123',
                    'order_id' => 'order-mock-1779',
                    'gross_amount' => '15000.00',
                    'qr_string' => 'mock-qr-string-data',
                    'expiry_time' => '2026-05-23 12:00:00',
                ],
            ], 200),
        ]);

        $this->actingAs($this->cashier);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->organization->slug]);

        $response = $this->postJson(route('api.v1.payments.store'), [
            'open_bill_id' => $this->bill->id,
            'method' => 'qris',
        ], [
            'X-Organization-Id' => $this->organization->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.reference_number', 'order-mock-1779');
        $response->assertJsonPath('data.metadata.qr_string', 'mock-qr-string-data');

        $this->assertDatabaseHas('payments', [
            'open_bill_id' => $this->bill->id,
            'method' => 'qris',
            'status' => 'pending',
            'reference_number' => 'order-mock-1779',
        ]);
    }

    public function test_customer_can_create_qris_payment(): void
    {
        Http::fake([
            'https://qris.sekeco.id/create' => Http::response([
                'ok' => true,
                'message' => 'payment dibuat',
                'data' => [
                    'status_code' => '201',
                    'status_message' => 'Qris transaction is created',
                    'transaction_id' => 'mock-tx-1234',
                    'order_id' => 'order-mock-1780',
                    'gross_amount' => '15000.00',
                    'qr_string' => 'mock-qr-string-customer',
                    'expiry_time' => '2026-05-23 12:00:00',
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('api.v1.customer.payments.store'), [], [
            'X-Customer-Session' => $this->session->session_token,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.reference_number', 'order-mock-1780');

        $this->assertDatabaseHas('payments', [
            'open_bill_id' => $this->bill->id,
            'method' => 'qris',
            'status' => 'pending',
            'reference_number' => 'order-mock-1780',
        ]);
    }

    public function test_cashier_can_check_status_and_complete_payment(): void
    {
        Event::fake([PaymentPaid::class]);

        $payment = Payment::create([
            'organization_id' => $this->organization->id,
            'open_bill_id' => $this->bill->id,
            'payment_number' => 'PAY-1111',
            'method' => 'qris',
            'status' => PaymentStatus::Pending,
            'amount' => 15000,
            'reference_number' => 'order-mock-1781',
        ]);

        Http::fake([
            'https://qris.sekeco.id/check*' => Http::response([
                'ok' => true,
                'message' => 'status transaksi',
                'data' => [
                    'status_code' => '200',
                    'transaction_id' => 'mock-tx-12345',
                    'order_id' => 'order-mock-1781',
                    'transaction_status' => 'settlement',
                    'fraud_status' => 'accept',
                ],
            ], 200),
        ]);

        $this->actingAs($this->cashier);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->organization->slug]);

        $response = $this->postJson(route('api.v1.payments.check', $payment->id), [], [
            'X-Organization-Id' => $this->organization->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);

        Event::assertDispatched(PaymentPaid::class, function ($event) use ($payment) {
            return $event->payment->id === $payment->id;
        });
    }

    public function test_customer_can_check_status_and_complete_payment(): void
    {
        Event::fake([PaymentPaid::class]);

        $payment = Payment::create([
            'organization_id' => $this->organization->id,
            'open_bill_id' => $this->bill->id,
            'payment_number' => 'PAY-2222',
            'method' => 'qris',
            'status' => PaymentStatus::Pending,
            'amount' => 15000,
            'reference_number' => 'order-mock-1782',
        ]);

        Http::fake([
            'https://qris.sekeco.id/check*' => Http::response([
                'ok' => true,
                'message' => 'status transaksi',
                'data' => [
                    'status_code' => '200',
                    'transaction_id' => 'mock-tx-12346',
                    'order_id' => 'order-mock-1782',
                    'transaction_status' => 'settlement',
                    'fraud_status' => 'accept',
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('api.v1.customer.payments.check', $payment->id), [], [
            'X-Customer-Session' => $this->session->session_token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);

        Event::assertDispatched(PaymentPaid::class);
    }

    public function test_cashier_can_cancel_pending_payment(): void
    {
        $payment = Payment::create([
            'organization_id' => $this->organization->id,
            'open_bill_id' => $this->bill->id,
            'payment_number' => 'PAY-3333',
            'method' => 'qris',
            'status' => PaymentStatus::Pending,
            'amount' => 15000,
            'reference_number' => 'order-mock-1783',
        ]);

        Http::fake([
            'https://qris.sekeco.id/cancel*' => Http::response([
                'ok' => true,
                'message' => 'transaksi dibatalkan',
                'data' => '200',
            ], 200),
        ]);

        $this->actingAs($this->cashier);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->organization->slug]);

        $response = $this->postJson(route('api.v1.payments.cancel', $payment->id), [], [
            'X-Organization-Id' => $this->organization->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
    }

    public function test_public_webhook_can_complete_payment(): void
    {
        Event::fake([PaymentPaid::class]);

        $payment = Payment::create([
            'organization_id' => $this->organization->id,
            'open_bill_id' => $this->bill->id,
            'payment_number' => 'PAY-4444',
            'method' => 'qris',
            'status' => PaymentStatus::Pending,
            'amount' => 15000,
            'reference_number' => 'order-mock-1784',
        ]);

        $response = $this->postJson(route('api.v1.payments.webhook'), [
            'order_id' => 'order-mock-1784',
            'transaction_status' => 'settlement',
            'gross_amount' => '15000.00',
            'transaction_id' => 'mock-webhook-tx-123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Pembayaran berhasil dikonfirmasi lunas.');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);

        Event::assertDispatched(PaymentPaid::class);
    }
}
