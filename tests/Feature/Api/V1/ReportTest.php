<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BillStatus;
use App\Enums\CustomerSessionStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\OpenBill;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private User $kitchen;
    private Organization $wpsOrg;
    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions & demo WPS resto
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->wpsOrg = Organization::where('slug', 'warung-padang-sekeco')->first();
        $this->owner = User::where('email', 'owner@santap.com')->first();
        $this->cashier = User::where('email', 'cashier@santap.com')->first();
        $this->kitchen = User::where('email', 'kitchen@santap.com')->first();

        // Create other organization to verify scoping
        $this->otherOrg = Organization::create([
            'name' => 'Resto Lainnya',
            'slug' => 'resto-lainnya',
        ]);
        
        $otherOwner = User::factory()->create();
        $this->otherOrg->users()->attach($otherOwner->id, ['role_name' => 'owner']);
        
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->otherOrg->id);
        $otherOwner->assignRole('owner');

        // 1. Seed sales data in WPS Org
        $wpsTable = DiningTable::where('organization_id', $this->wpsOrg->id)->first();
        $wpsMenu = Menu::where('organization_id', $this->wpsOrg->id)->first();

        $wpsBill = OpenBill::create([
            'organization_id' => $this->wpsOrg->id,
            'dining_table_id' => $wpsTable->id,
            'bill_number' => 'BILL-WPS-1',
            'status' => BillStatus::Closed,
            'subtotal_amount' => 50000,
            'total_amount' => 50000,
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
        ]);

        $wpsOrder = Order::create([
            'organization_id' => $this->wpsOrg->id,
            'open_bill_id' => $wpsBill->id,
            'dining_table_id' => $wpsTable->id,
            'order_number' => 'ORD-WPS-1',
            'status' => \App\Enums\OrderStatus::Served,
            'subtotal_amount' => 50000,
            'total_amount' => 50000,
        ]);

        OrderItem::create([
            'organization_id' => $this->wpsOrg->id,
            'order_id' => $wpsOrder->id,
            'menu_id' => $wpsMenu->id,
            'menu_name_snapshot' => $wpsMenu->name,
            'menu_price_snapshot' => $wpsMenu->price,
            'quantity' => 2,
            'subtotal_amount' => 50000,
        ]);

        Payment::create([
            'organization_id' => $this->wpsOrg->id,
            'open_bill_id' => $wpsBill->id,
            'payment_number' => 'PAY-WPS-1',
            'method' => \App\Enums\PaymentMethod::Qris,
            'status' => PaymentStatus::Paid,
            'amount' => 50000,
            'paid_amount' => 50000,
            'paid_at' => now()->subHour(),
        ]);

        // 2. Seed sales data in Other Org (Scoping test data)
        $otherTable = DiningTable::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Meja Lain',
            'code' => 'TL1',
            'status' => TableStatus::Available,
        ]);

        $otherCategory = MenuCategory::create([
            'organization_id' => $this->otherOrg->id,
            'name' => 'Lain Category',
            'slug' => 'lain',
        ]);

        $otherMenu = Menu::create([
            'organization_id' => $this->otherOrg->id,
            'menu_category_id' => $otherCategory->id,
            'name' => 'Menu Lain',
            'slug' => 'menu-lain',
            'price' => 100000,
            'status' => \App\Enums\MenuStatus::Active,
        ]);

        $otherBill = OpenBill::create([
            'organization_id' => $this->otherOrg->id,
            'dining_table_id' => $otherTable->id,
            'bill_number' => 'BILL-OTH-1',
            'status' => BillStatus::Closed,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
        ]);

        $otherOrder = Order::create([
            'organization_id' => $this->otherOrg->id,
            'open_bill_id' => $otherBill->id,
            'dining_table_id' => $otherTable->id,
            'order_number' => 'ORD-OTH-1',
            'status' => \App\Enums\OrderStatus::Served,
            'subtotal_amount' => 100000,
            'total_amount' => 100000,
        ]);

        OrderItem::create([
            'organization_id' => $this->otherOrg->id,
            'order_id' => $otherOrder->id,
            'menu_id' => $otherMenu->id,
            'menu_name_snapshot' => $otherMenu->name,
            'menu_price_snapshot' => $otherMenu->price,
            'quantity' => 1,
            'subtotal_amount' => 100000,
        ]);

        Payment::create([
            'organization_id' => $this->otherOrg->id,
            'open_bill_id' => $otherBill->id,
            'payment_number' => 'PAY-OTH-1',
            'method' => \App\Enums\PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'amount' => 100000,
            'paid_amount' => 100000,
            'paid_at' => now()->subHour(),
        ]);
    }

    public function test_owner_can_view_sales_summary_scoped_to_organization(): void
    {
        $this->actingAs($this->owner);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->wpsOrg->slug]);

        $response = $this->getJson(route('api.v1.reports.sales-summary'), [
            'X-Organization-Id' => $this->wpsOrg->id,
        ]);

        $response->assertJsonPath('data.total_sales', 50000);
        $response->assertJsonPath('data.total_orders', 1);
        $response->assertJsonPath('data.total_bills', 1);
        $response->assertJsonPath('data.average_bill_amount', 50000);
    }

    public function test_owner_can_view_daily_sales_scoped_to_organization(): void
    {
        $this->actingAs($this->owner);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->wpsOrg->slug]);

        $response = $this->getJson(route('api.v1.reports.daily-sales'), [
            'X-Organization-Id' => $this->wpsOrg->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.total_amount', 50000);
    }

    public function test_owner_can_view_menu_sales_scoped_to_organization(): void
    {
        $this->actingAs($this->owner);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->wpsOrg->slug]);

        $response = $this->getJson(route('api.v1.reports.menu-sales'), [
            'X-Organization-Id' => $this->wpsOrg->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.total_revenue', 50000);
    }

    public function test_owner_can_view_payment_methods_scoped_to_organization(): void
    {
        $this->actingAs($this->owner);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->wpsOrg->slug]);

        $response = $this->getJson(route('api.v1.reports.payment-methods'), [
            'X-Organization-Id' => $this->wpsOrg->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.payment_method', 'qris');
        $response->assertJsonPath('data.0.total_amount', 50000);
    }

    public function test_cashier_can_view_reports(): void
    {
        $this->actingAs($this->cashier);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->wpsOrg->slug]);

        $response = $this->getJson(route('api.v1.reports.sales-summary'), [
            'X-Organization-Id' => $this->wpsOrg->id,
        ]);

        $response->assertStatus(200);
    }

    public function test_kitchen_is_forbidden_from_viewing_reports(): void
    {
        $this->actingAs($this->kitchen);
        $this->postJson(route('api.v1.context.switch'), ['organization_slug' => $this->wpsOrg->slug]);

        $response = $this->getJson(route('api.v1.reports.sales-summary'), [
            'X-Organization-Id' => $this->wpsOrg->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Anda tidak memiliki hak akses untuk tindakan ini.');
    }
}
