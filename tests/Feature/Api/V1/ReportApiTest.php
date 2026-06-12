<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BillStatus;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Enums\MenuType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Organization $otherOrganization;

    private User $owner;

    private User $cashier;

    private User $kitchen;

    private User $otherOwner;

    private int $orderSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Reports Resto',
            'slug' => 'reports-resto',
            'is_active' => true,
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->otherOrganization = Organization::create([
            'name' => 'Other Reports Resto',
            'slug' => 'other-reports-resto',
            'is_active' => true,
            'timezone' => 'Asia/Jakarta',
        ]);

        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->cashier = User::factory()->create(['name' => 'Cashier']);
        $this->kitchen = User::factory()->create(['name' => 'Kitchen']);
        $this->otherOwner = User::factory()->create(['name' => 'Other Owner']);

        $this->attachMember($this->organization, $this->owner, 'owner');
        $this->attachMember($this->organization, $this->cashier, 'cashier');
        $this->attachMember($this->organization, $this->kitchen, 'kitchen');
        $this->attachMember($this->otherOrganization, $this->otherOwner, 'owner');
    }

    public function test_reports_require_authentication(): void
    {
        $this->withHeader('X-Org-ID', (string) $this->organization->id)
            ->getJson(route('api.v1.reports.financial.summary', $this->dateQuery()))
            ->assertUnauthorized();
    }

    public function test_only_owner_can_access_reports(): void
    {
        $this->actingAs($this->cashier)
            ->withHeader('X-Org-ID', (string) $this->organization->id)
            ->getJson(route('api.v1.reports.financial.summary', $this->dateQuery()))
            ->assertForbidden();

        $this->actingAs($this->kitchen)
            ->withHeader('X-Org-ID', (string) $this->organization->id)
            ->getJson(route('api.v1.reports.products.bestsellers', $this->dateQuery()))
            ->assertForbidden();

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.financial.summary', $this->dateQuery()))
            ->assertOk();
    }

    public function test_report_date_filters_are_validated(): void
    {
        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.financial.summary', [
                'start_date' => '2026/06/01',
                'end_date' => '2026-06-30',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.financial.summary', [
                'start_date' => '2026-06-30',
                'end_date' => '2026-06-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.financial.summary', [
                'start_date' => '2026-01-01',
                'end_date' => '2027-01-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_financial_summary_uses_paid_at_cancelled_at_and_organization_scope(): void
    {
        $this->makeOrder($this->organization, [
            'order_type' => OrderType::OpenBill,
            'payment_method' => 'qris',
            'subtotal_amount' => 100000,
            'discount_amount' => 5000,
            'tax_amount' => 10000,
            'service_charge_amount' => 5000,
            'total_amount' => 110000,
            'paid_at' => '2026-06-01 15:00:00',
            'created_by' => $this->cashier->id,
        ]);

        $this->makeOrder($this->organization, [
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => 'cash',
            'total_amount' => 999999,
            'paid_at' => null,
            'created_by' => $this->cashier->id,
        ]);

        $this->makeOrder($this->organization, [
            'order_status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::Failed,
            'payment_method' => 'qris',
            'total_amount' => 888888,
            'paid_at' => '2026-06-01 10:00:00',
            'cancelled_at' => '2026-06-01 11:00:00',
        ]);

        $this->makeOrder($this->organization, [
            'order_status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::Cancelled,
            'payment_method' => 'qris',
            'total_amount' => 70000,
            'paid_at' => null,
            'cancelled_at' => '2026-06-01 01:00:00',
        ]);

        $this->makeOrder($this->otherOrganization, [
            'payment_method' => 'cash',
            'total_amount' => 999999,
            'paid_at' => '2026-06-01 03:00:00',
            'created_by' => $this->otherOwner->id,
        ]);

        $response = $this->actingAsOwner()
            ->getJson(route('api.v1.reports.financial.summary', array_merge($this->dateQuery(), [
                'group_by' => 'daily',
                'organization_id' => $this->otherOrganization->id,
            ])));

        $response->assertOk()
            ->assertJsonPath('data.summary.total_revenue', 110000)
            ->assertJsonPath('data.summary.total_subtotal', 100000)
            ->assertJsonPath('data.summary.total_discount', 5000)
            ->assertJsonPath('data.summary.total_tax', 10000)
            ->assertJsonPath('data.summary.total_service_charge', 5000)
            ->assertJsonPath('data.summary.total_transactions', 1)
            ->assertJsonPath('data.summary.transaction_count_by_type.open_bill', 1)
            ->assertJsonPath('data.summary.transaction_count_by_type.cashier_order', 0)
            ->assertJsonPath('data.summary.payment_method_breakdown.qris.count', 1)
            ->assertJsonPath('data.summary.payment_method_breakdown.qris.amount', 110000)
            ->assertJsonPath('data.summary.cancelled_transactions.count', 2)
            ->assertJsonPath('data.summary.cancelled_transactions.total_amount', 958888)
            ->assertJsonPath('data.breakdown.0.date', '2026-06-01')
            ->assertJsonPath('data.breakdown.0.revenue', 110000)
            ->assertJsonPath('data.meta.revenue_date_basis', 'paid_at');
    }

    public function test_financial_grouping_and_timezone_boundary_are_consistent(): void
    {
        $this->makeOrder($this->organization, [
            'payment_method' => 'cash',
            'total_amount' => 123000,
            'paid_at' => '2026-06-01 17:30:00',
            'created_by' => $this->cashier->id,
        ]);

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.financial.summary', [
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-01',
            ]))
            ->assertOk()
            ->assertJsonPath('data.summary.total_revenue', 0);

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.financial.summary', [
                'start_date' => '2026-06-02',
                'end_date' => '2026-06-02',
                'group_by' => 'daily',
            ]))
            ->assertOk()
            ->assertJsonPath('data.summary.total_revenue', 123000)
            ->assertJsonPath('data.breakdown.0.date', '2026-06-02');

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.financial.summary', [
                'start_date' => '2026-06-02',
                'end_date' => '2026-06-30',
                'group_by' => 'weekly',
            ]))
            ->assertOk()
            ->assertJsonPath('data.breakdown.0.date', '2026-06-01');

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.financial.summary', [
                'start_date' => '2026-06-02',
                'end_date' => '2026-06-30',
                'group_by' => 'monthly',
            ]))
            ->assertOk()
            ->assertJsonPath('data.breakdown.0.date', '2026-06-01');
    }

    public function test_product_reports_use_paid_valid_items_and_historical_snapshots(): void
    {
        $nasi = $this->makeProduct($this->organization, 'Nasi Goreng', 25000);
        $sate = $this->makeProduct($this->organization, 'Sate Ayam', 30000);
        $soto = $this->makeProduct($this->organization, 'Soto Lama', 18000);
        $unavailable = $this->makeProduct($this->organization, 'Tidak Aktif', 20000, false);

        $oldSotoOrder = $this->makeOrder($this->organization, [
            'total_amount' => 18000,
            'paid_at' => '2026-05-31 10:00:00',
            'created_by' => $this->cashier->id,
        ]);
        $this->makeItem($oldSotoOrder, $soto, 1, 18000);

        $paidOrder = $this->makeOrder($this->organization, [
            'order_type' => OrderType::CashierOrder,
            'total_amount' => 50000,
            'paid_at' => '2026-06-05 04:00:00',
            'created_by' => $this->cashier->id,
        ]);
        $this->makeItem($paidOrder, $nasi, 2, 30000);
        $this->makeItem($paidOrder, $sate, 1, 20000);
        $this->makeItem($paidOrder, $sate, 99, 990000, ItemStatus::Cancelled);

        $openBill = $this->makeOrder($this->organization, [
            'order_type' => OrderType::OpenBill,
            'bill_status' => BillStatus::Closed,
            'total_amount' => 45000,
            'paid_at' => '2026-06-06 04:00:00',
            'created_by' => $this->cashier->id,
        ]);
        $this->makeItem($openBill, $nasi, 1, 15000);
        $this->makeItem($openBill, $nasi, 2, 30000);

        $unpaidOrder = $this->makeOrder($this->organization, [
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => 999999,
            'paid_at' => null,
            'created_by' => $this->cashier->id,
        ]);
        $this->makeItem($unpaidOrder, $nasi, 10, 999999);

        $nasi->update(['price' => 999999]);

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.products.bestsellers', $this->dateQuery()))
            ->assertOk()
            ->assertJsonPath('data.products.0.id', $nasi->id)
            ->assertJsonPath('data.products.0.total_qty', 5)
            ->assertJsonPath('data.products.0.total_revenue', 75000)
            ->assertJsonPath('data.products.1.id', $sate->id)
            ->assertJsonPath('data.products.1.total_qty', 1)
            ->assertJsonPath('data.products.1.total_revenue', 20000);

        $noSales = $this->actingAsOwner()
            ->getJson(route('api.v1.reports.products.no-sales', $this->dateQuery()))
            ->assertOk();

        $noSales->assertJsonFragment([
            'id' => $soto->id,
            'name' => 'Soto Lama',
            'price' => 18000,
            'last_sold_date' => '2026-05-31',
        ]);
        $this->assertNotContains($unavailable->id, collect($noSales->json('data.products'))->pluck('id'));

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.products.by-category', $this->dateQuery()))
            ->assertOk()
            ->assertJsonPath('data.categories.0.id', null)
            ->assertJsonPath('data.categories.0.name', 'Uncategorized')
            ->assertJsonPath('data.categories.0.total_qty', 6)
            ->assertJsonPath('data.categories.0.total_revenue', 95000)
            ->assertJsonPath('data.categories.0.percentage', 100);
    }

    public function test_product_trend_is_zero_filled_and_scoped_to_organization(): void
    {
        $product = $this->makeProduct($this->organization, 'Es Teh', 5000);
        $otherProduct = $this->makeProduct($this->otherOrganization, 'Produk Organisasi Lain', 5000);

        $dayOneOrder = $this->makeOrder($this->organization, [
            'total_amount' => 10000,
            'paid_at' => '2026-06-01 04:00:00',
            'created_by' => $this->cashier->id,
        ]);
        $this->makeItem($dayOneOrder, $product, 2, 10000);

        $dayThreeOrder = $this->makeOrder($this->organization, [
            'total_amount' => 15000,
            'paid_at' => '2026-06-03 04:00:00',
            'created_by' => $this->cashier->id,
        ]);
        $this->makeItem($dayThreeOrder, $product, 3, 15000);

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.products.trend', [
                'product_id' => $product->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-03',
            ]))
            ->assertOk()
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.trend.0.date', '2026-06-01')
            ->assertJsonPath('data.trend.0.qty', 2)
            ->assertJsonPath('data.trend.1.date', '2026-06-02')
            ->assertJsonPath('data.trend.1.qty', 0)
            ->assertJsonPath('data.trend.1.revenue', 0)
            ->assertJsonPath('data.trend.2.date', '2026-06-03')
            ->assertJsonPath('data.trend.2.revenue', 15000);

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.products.trend', [
                'product_id' => $otherProduct->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-03',
            ]))
            ->assertNotFound();
    }

    public function test_operational_reports_keep_cashier_and_self_service_separate(): void
    {
        $cashOrder = $this->makeOrder($this->organization, [
            'payment_method' => 'cash',
            'total_amount' => 100000,
            'paid_at' => '2026-06-01 03:00:00',
            'created_by' => $this->cashier->id,
        ]);

        $qrisSelfServiceOrder = $this->makeOrder($this->organization, [
            'order_type' => OrderType::TableOrder,
            'payment_method' => 'qris',
            'total_amount' => 200000,
            'paid_at' => '2026-06-01 04:00:00',
            'created_by' => null,
        ]);

        $this->makeOrder($this->otherOrganization, [
            'payment_method' => 'cash',
            'total_amount' => 999999,
            'paid_at' => '2026-06-01 03:00:00',
            'created_by' => $this->otherOwner->id,
        ]);

        $cashierResponse = $this->actingAsOwner()
            ->getJson(route('api.v1.reports.operational.by-cashier', $this->dateQuery()))
            ->assertOk();

        $cashiers = collect($cashierResponse->json('data.cashiers'))->keyBy('name');

        $this->assertSame($this->cashier->id, $cashiers['Cashier']['id']);
        $this->assertSame(1, $cashiers['Cashier']['total_transactions']);
        $this->assertSame(100000, $cashiers['Cashier']['cash_amount']);
        $this->assertNull($cashiers['Unassigned']['id']);
        $this->assertSame(1, $cashiers['Unassigned']['total_transactions']);
        $this->assertSame(200000, $cashiers['Unassigned']['qris_amount']);
        $this->assertDatabaseHas('orders', ['id' => $cashOrder->id]);
        $this->assertDatabaseHas('orders', ['id' => $qrisSelfServiceOrder->id]);

        $peakHours = $this->actingAsOwner()
            ->getJson(route('api.v1.reports.operational.peak-hours', $this->dateQuery()))
            ->assertOk();

        $hours = collect($peakHours->json('data.hours'))->keyBy('hour');
        $this->assertCount(24, $hours);
        $this->assertSame(1, $hours[10]['transactions']);
        $this->assertSame(100000, $hours[10]['revenue']);
        $this->assertSame(1, $hours[11]['transactions']);
        $this->assertSame(200000, $hours[11]['revenue']);
    }

    public function test_empty_category_report_has_zero_percentage_and_limit_is_capped(): void
    {
        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.products.by-category', $this->dateQuery()))
            ->assertOk()
            ->assertJsonPath('data.categories.0.total_revenue', 0)
            ->assertJsonPath('data.categories.0.percentage', 0);

        $this->actingAsOwner()
            ->getJson(route('api.v1.reports.products.bestsellers', array_merge($this->dateQuery(), [
                'limit' => 51,
            ])))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }

    private function actingAsOwner(): self
    {
        return $this->actingAs($this->owner)
            ->withHeader('X-Org-ID', (string) $this->organization->id);
    }

    private function attachMember(Organization $organization, User $user, string $role): void
    {
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    /**
     * @return array{start_date: string, end_date: string}
     */
    private function dateQuery(): array
    {
        return [
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ];
    }

    private function makeProduct(
        Organization $organization,
        string $name,
        int $price,
        bool $isAvailable = true,
    ): Menu {
        return Menu::create([
            'organization_id' => $organization->id,
            'type' => MenuType::Product,
            'name' => $name,
            'price' => $price,
            'is_available' => $isAvailable,
            'sort_order' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeOrder(Organization $organization, array $attributes = []): Order
    {
        $paymentStatus = $attributes['payment_status'] ?? PaymentStatus::Paid;
        $orderStatus = $attributes['order_status'] ?? OrderStatus::Confirmed;
        $paidAt = array_key_exists('paid_at', $attributes)
            ? $attributes['paid_at']
            : '2026-06-01 03:00:00';
        $cancelledAt = $attributes['cancelled_at'] ?? null;

        return Order::create([
            'order_number' => 'RPT-'.str_pad((string) $this->orderSequence++, 5, '0', STR_PAD_LEFT).'-'.Str::random(4),
            'public_token' => $attributes['public_token'] ?? Str::random(32),
            'organization_id' => $organization->id,
            'dining_table_id' => null,
            'created_by' => $attributes['created_by'] ?? null,
            'order_type' => $attributes['order_type'] ?? OrderType::CashierOrder,
            'bill_status' => $attributes['bill_status'] ?? BillStatus::Closed,
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'tax_rate_snapshot' => 0,
            'service_charge_rate_snapshot' => 0,
            'subtotal_amount' => $attributes['subtotal_amount'] ?? $attributes['total_amount'] ?? 0,
            'discount_amount' => $attributes['discount_amount'] ?? 0,
            'tax_amount' => $attributes['tax_amount'] ?? 0,
            'service_charge_amount' => $attributes['service_charge_amount'] ?? 0,
            'total_amount' => $attributes['total_amount'] ?? 0,
            'payment_amount' => $attributes['payment_amount'] ?? $attributes['total_amount'] ?? 0,
            'change_amount' => 0,
            'payment_method' => $attributes['payment_method'] ?? 'cash',
            'paid_at' => is_string($paidAt) ? CarbonImmutable::parse($paidAt, 'UTC') : $paidAt,
            'cancelled_at' => is_string($cancelledAt) ? CarbonImmutable::parse($cancelledAt, 'UTC') : $cancelledAt,
            'opened_at' => $attributes['opened_at'] ?? CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
            'closed_at' => $attributes['closed_at'] ?? null,
        ]);
    }

    private function makeItem(
        Order $order,
        Menu $product,
        int $quantity,
        int $subtotal,
        ItemStatus $status = ItemStatus::Pending,
    ): OrderItem {
        $unitPrice = $quantity > 0 ? (int) round($subtotal / $quantity) : 0;

        return OrderItem::create([
            'order_id' => $order->id,
            'menu_id' => $product->id,
            'parent_item_id' => null,
            'item_type' => ItemType::Product,
            'name' => $product->name,
            'base_price' => $unitPrice,
            'variant_total' => 0,
            'unit_price' => $unitPrice,
            'price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'item_status' => $status,
        ]);
    }
}
