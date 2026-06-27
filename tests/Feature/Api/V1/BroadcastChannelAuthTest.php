<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private DiningTable $table;
    private User $memberUser;
    private User $nonMemberUser;
    private Order $openBill;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'mock-key',
            'broadcasting.connections.reverb.secret' => 'mock-secret',
            'broadcasting.connections.reverb.app_id' => 'mock-app-id',
        ]);

        \Illuminate\Support\Facades\Broadcast::purge();

        // 1. Setup Organization
        $this->org = Organization::create([
            'name'      => 'Broadcast Resto',
            'slug'      => 'broadcast-resto',
            'is_active' => true,
        ]);

        // 2. Setup Users
        $this->memberUser = User::factory()->create();
        $this->nonMemberUser = User::factory()->create();

        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->memberUser->id,
            'role'            => 'cashier',
        ]);

        // 3. Setup Table
        $this->table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name'            => 'Meja 1',
            'code'            => 'T1',
            'qr_token'        => Str::random(32),
            'is_active'       => true,
        ]);

        // 4. Setup Open Bill
        $this->openBill = Order::create([
            'order_number'                 => Order::generateOrderNumber($this->org->id),
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->memberUser->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Open,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 0,
            'opened_at'                    => now(),
        ]);
    }

    /**
     * Test Broadcast auth for Customer Web: open-bill.{billId}
     */
    public function test_customer_web_can_auth_open_bill_channel_with_valid_token(): void
    {
        $response = $this->withHeader('X-Public-Token', $this->openBill->public_token)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-open-bill.{$this->openBill->id}",
                'socket_id'    => '1234.5678',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth']);
    }

    public function test_customer_web_cannot_auth_open_bill_channel_with_invalid_token(): void
    {
        $response = $this->withHeader('X-Public-Token', 'invalid-token')
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-open-bill.{$this->openBill->id}",
                'socket_id'    => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    public function test_customer_web_cannot_auth_open_bill_channel_without_token(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-open-bill.{$this->openBill->id}",
            'socket_id'    => '1234.5678',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test Broadcast auth for Mobile Cashier/Store: organization.{orgId}
     */
    public function test_store_staff_can_auth_organization_channel(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-organization.{$this->org->id}",
                'socket_id'    => '1234.5678',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth']);
    }

    public function test_non_store_staff_cannot_auth_organization_channel(): void
    {
        $response = $this->actingAs($this->nonMemberUser)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-organization.{$this->org->id}",
                'socket_id'    => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_auth_organization_channel(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-organization.{$this->org->id}",
            'socket_id'    => '1234.5678',
        ]);

        $response->assertStatus(403);
    }
}
