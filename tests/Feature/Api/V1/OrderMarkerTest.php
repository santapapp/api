<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BillStatus;
use App\Enums\MenuType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Skenario fitur Nomor Penanda Pesanan.
 *
 * Penting:
 * - Hanya berlaku untuk cashier_order dan open_bill.
 * - table_order sama sekali tidak terdampak.
 * - Mode disabled: nilai input diabaikan, disimpan null.
 * - Mode optional: diisi boleh, kosong boleh.
 * - Mode required: wajib diisi dalam rentang 1–max.
 */
class OrderMarkerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'      => 'Marker Resto',
            'slug'      => 'marker-resto',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create();

        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->cashier->id,
            'role'            => 'cashier',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function actingAsCashier(): self
    {
        return $this->actingAs($this->cashier)
            ->withHeader('X-Org-ID', (string) $this->org->id);
    }

    private function setOrgMarkerMode(string $mode, ?int $max = null): void
    {
        $this->org->update([
            'order_marker_mode'       => $mode,
            'order_marker_max_number' => $max,
        ]);
    }

    private function createOrder(array $extra = []): array
    {
        return array_merge([
            'order_type' => OrderType::CashierOrder->value,
        ], $extra);
    }

    // ── Mode: disabled ───────────────────────────────────────────────

    public function test_disabled_mode_allows_creating_without_marker(): void
    {
        $this->setOrgMarkerMode('disabled');

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder());

        $response->assertCreated();
        $response->assertJsonPath('data.order_marker_number', null);
    }

    public function test_disabled_mode_ignores_marker_and_stores_null(): void
    {
        $this->setOrgMarkerMode('disabled');

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder([
                'order_marker_number' => 5,
            ]));

        $response->assertCreated();
        // Input diterima tapi disimpan null (tidak error, tidak disimpan)
        $response->assertJsonPath('data.order_marker_number', null);
    }

    // ── Mode: optional ───────────────────────────────────────────────

    public function test_optional_mode_allows_creating_without_marker(): void
    {
        $this->setOrgMarkerMode('optional', 30);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder());

        $response->assertCreated();
        $response->assertJsonPath('data.order_marker_number', null);
    }

    public function test_optional_mode_allows_valid_marker_number(): void
    {
        $this->setOrgMarkerMode('optional', 30);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder([
                'order_marker_number' => 15,
            ]));

        $response->assertCreated();
        $response->assertJsonPath('data.order_marker_number', 15);
    }

    public function test_optional_mode_rejects_marker_above_max(): void
    {
        $this->setOrgMarkerMode('optional', 30);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder([
                'order_marker_number' => 31,
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_marker_number']);
        $this->assertStringContainsString(
            'tidak boleh lebih dari',
            $response->json('errors.order_marker_number.0')
        );
    }

    public function test_optional_mode_rejects_marker_below_one(): void
    {
        $this->setOrgMarkerMode('optional', 30);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder([
                'order_marker_number' => 0,
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_marker_number']);
        $this->assertStringContainsString(
            'minimal 1',
            $response->json('errors.order_marker_number.0')
        );
    }

    // ── Mode: required ───────────────────────────────────────────────

    public function test_required_mode_rejects_missing_marker(): void
    {
        $this->setOrgMarkerMode('required', 30);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_marker_number']);
        $this->assertStringContainsString(
            'wajib diisi',
            $response->json('errors.order_marker_number.0')
        );
    }

    public function test_required_mode_allows_valid_marker(): void
    {
        $this->setOrgMarkerMode('required', 30);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder([
                'order_marker_number' => 7,
            ]));

        $response->assertCreated();
        $response->assertJsonPath('data.order_marker_number', 7);
    }

    public function test_required_mode_rejects_marker_above_max(): void
    {
        $this->setOrgMarkerMode('required', 30);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder([
                'order_marker_number' => 99,
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_marker_number']);
    }

    // ── Open Bill ────────────────────────────────────────────────────

    public function test_open_bill_required_mode_validates_marker(): void
    {
        $this->setOrgMarkerMode('required', 20);

        $table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name'            => 'Meja 1',
            'code'            => 'M1',
            'qr_token'        => Str::random(32),
            'is_active'       => true,
        ]);

        // Tanpa marker → harus gagal
        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), [
                'order_type'      => OrderType::OpenBill->value,
                'dining_table_id' => $table->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_marker_number']);
    }

    public function test_open_bill_with_valid_marker_stores_correctly(): void
    {
        $this->setOrgMarkerMode('optional', 20);

        $table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name'            => 'Meja 2',
            'code'            => 'M2',
            'qr_token'        => Str::random(32),
            'is_active'       => true,
        ]);

        $response = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), [
                'order_type'          => OrderType::OpenBill->value,
                'dining_table_id'     => $table->id,
                'order_marker_number' => 3,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.order_marker_number', 3);
        $response->assertJsonPath('data.order_type', OrderType::OpenBill->value);
        $response->assertJsonPath('data.bill_status', BillStatus::Open->value);
    }

    // ── Organization response berisi config order_marker ─────────────

    public function test_organization_response_contains_order_marker_config(): void
    {
        $this->setOrgMarkerMode('optional', 25);

        $response = $this->actingAsCashier()
            ->getJson(route('api.v1.organizations.show'));

        $response->assertOk();
        $response->assertJsonPath('data.order_marker.mode', 'optional');
        $response->assertJsonPath('data.order_marker.max_number', 25);
        $response->assertJsonPath('data.order_marker.label', 'Nomor Penanda Pesanan');
    }

    public function test_organization_defaults_to_disabled_mode(): void
    {
        // Org baru tanpa set mode — default 'disabled'
        $response = $this->actingAsCashier()
            ->getJson(route('api.v1.organizations.show'));

        $response->assertOk();
        $response->assertJsonPath('data.order_marker.mode', 'disabled');
        $response->assertJsonPath('data.order_marker.max_number', null);
    }

    // ── Order detail & list berisi order_marker_number ───────────────

    public function test_order_detail_includes_marker_number_field(): void
    {
        $this->setOrgMarkerMode('optional', 30);

        $createResponse = $this->actingAsCashier()
            ->postJson(route('api.v1.cashier.orders.store'), $this->createOrder([
                'order_marker_number' => 12,
            ]));

        $createResponse->assertCreated();
        $orderId = $createResponse->json('data.id');

        $showResponse = $this->actingAsCashier()
            ->getJson(route('api.v1.cashier.orders.show', $orderId));

        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.order_marker_number', 12);
    }
}
