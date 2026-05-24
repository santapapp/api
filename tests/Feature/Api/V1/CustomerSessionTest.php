<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Organization;
use App\Models\TableQrCode;
use App\Models\CustomerSession;
use App\Models\OpenBill;
use App\Enums\TableStatus;
use App\Enums\QrCodeStatus;
use App\Enums\CustomerSessionStatus;
use App\Enums\BillStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSessionTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;
    protected Organization $orgB;
    protected DiningTable $tableA;
    protected DiningTable $tableB;
    protected TableQrCode $qrA;
    protected TableQrCode $qrB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Buat 2 Organisasi
        $this->orgA = Organization::factory()->create(['name' => 'Resto A', 'slug' => 'resto-a']);
        $this->orgB = Organization::factory()->create(['name' => 'Resto B', 'slug' => 'resto-b']);

        // 2. Buat Meja
        $this->tableA = DiningTable::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Meja A1',
            'code' => 'A1',
            'capacity' => 2,
            'status' => TableStatus::Available,
        ]);

        $this->tableB = DiningTable::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Meja B1',
            'code' => 'B1',
            'capacity' => 4,
            'status' => TableStatus::Available,
        ]);

        // 3. Buat QR
        $this->qrA = TableQrCode::create([
            'organization_id' => $this->orgA->id,
            'dining_table_id' => $this->tableA->id,
            'qr_token' => 'token-a-1234',
            'qr_url' => 'https://santap.app/o/resto-a/t/A1?qr=token-a-1234',
            'status' => QrCodeStatus::Active,
        ]);

        $this->qrB = TableQrCode::create([
            'organization_id' => $this->orgB->id,
            'dining_table_id' => $this->tableB->id,
            'qr_token' => 'token-b-5678',
            'qr_url' => 'https://santap.app/o/resto-b/t/B1?qr=token-b-5678',
            'status' => QrCodeStatus::Active,
        ]);
    }

    public function test_customer_can_start_session_with_valid_qr_and_open_new_bill(): void
    {
        $response = $this->postJson('/api/v1/customer/sessions/start', [
            'organization_slug' => 'resto-a',
            'table_code' => 'A1',
            'qr_token' => 'token-a-1234',
            'client_label' => 'iPhone Customer',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'session_token',
            'expires_at',
            'organization',
            'table',
            'open_bill',
        ]);

        $sessionToken = $response->json('session_token');
        $billId = $response->json('open_bill.id');

        // Pastikan Sesi Pelanggan tersimpan di DB
        $this->assertDatabaseHas('customer_sessions', [
            'session_token' => $sessionToken,
            'status' => CustomerSessionStatus::Active->value,
            'open_bill_id' => $billId,
        ]);

        // Pastikan Open Bill aktif dibuat
        $this->assertDatabaseHas('open_bills', [
            'id' => $billId,
            'dining_table_id' => $this->tableA->id,
            'status' => BillStatus::Open->value,
        ]);

        // Pastikan meja menjadi occupied
        $this->assertEquals(TableStatus::Occupied, $this->tableA->fresh()->status);
    }

    public function test_subsequent_customer_joins_existing_open_bill_on_same_table(): void
    {
        // Jalankan scan pertama (membuat open bill)
        $this->postJson('/api/v1/customer/sessions/start', [
            'organization_slug' => 'resto-a',
            'table_code' => 'A1',
            'qr_token' => 'token-a-1234',
        ]);

        $activeBill = OpenBill::where('dining_table_id', $this->tableA->id)
            ->where('status', BillStatus::Open)
            ->first();
        $this->assertNotNull($activeBill);

        // Jalankan scan kedua dari device berbeda di meja yang sama
        $response = $this->postJson('/api/v1/customer/sessions/start', [
            'organization_slug' => 'resto-a',
            'table_code' => 'A1',
            'qr_token' => 'token-a-1234',
            'client_label' => 'Android Customer',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('open_bill.id', $activeBill->id);

        // Pastikan ada 2 sesi terikat pada bill yang sama
        $this->assertEquals(2, CustomerSession::where('open_bill_id', $activeBill->id)->count());
    }

    public function test_customer_cannot_start_session_with_invalid_or_revoked_qr(): void
    {
        // 1. QR Salah
        $response = $this->postJson('/api/v1/customer/sessions/start', [
            'organization_slug' => 'resto-a',
            'table_code' => 'A1',
            'qr_token' => 'salah-token',
        ]);
        $response->assertStatus(400);

        // 2. QR Telah Direvoke
        $this->qrA->update(['status' => QrCodeStatus::Revoked]);

        $response = $this->postJson('/api/v1/customer/sessions/start', [
            'organization_slug' => 'resto-a',
            'table_code' => 'A1',
            'qr_token' => 'token-a-1234',
        ]);
        $response->assertStatus(400);
    }

    public function test_customer_endpoints_require_valid_session_header(): void
    {
        // Request tanpa header X-Customer-Session
        $response = $this->getJson('/api/v1/customer/sessions/current');
        $response->assertStatus(401);

        // Request dengan header palsu
        $response = $this->withHeader('X-Customer-Session', 'palsu-token')
            ->getJson('/api/v1/customer/sessions/current');
        $response->assertStatus(401);
    }

    public function test_customer_can_get_current_session_and_scoped_menu(): void
    {
        // Setup menu dan kategori di Resto A
        $categoryA = MenuCategory::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Kopi A',
            'slug' => 'kopi-a',
            'status' => 'active',
        ]);
        $menuA = Menu::create([
            'organization_id' => $this->orgA->id,
            'menu_category_id' => $categoryA->id,
            'name' => 'Kopi Susu A',
            'slug' => 'kopi-susu-a',
            'price' => 12000,
            'status' => 'active',
        ]);

        // Setup menu dan kategori di Resto B (untuk memverifikasi kebocoran scoping)
        $categoryB = MenuCategory::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Kopi B',
            'slug' => 'kopi-b',
            'status' => 'active',
        ]);
        Menu::create([
            'organization_id' => $this->orgB->id,
            'menu_category_id' => $categoryB->id,
            'name' => 'Kopi Hitam B',
            'slug' => 'kopi-hitam-b',
            'price' => 10000,
            'status' => 'active',
        ]);

        // Start session di Resto A
        $startResponse = $this->postJson('/api/v1/customer/sessions/start', [
            'organization_slug' => 'resto-a',
            'table_code' => 'A1',
            'qr_token' => 'token-a-1234',
        ]);
        $token = $startResponse->json('session_token');

        // 1. Cek detail current session
        $response = $this->withHeader('X-Customer-Session', $token)
            ->getJson('/api/v1/customer/sessions/current');
        $response->assertStatus(200);
        $response->assertJsonPath('data.session_token', $token);

        // 2. Cek list menu (harus tersaring ke Resto A saja)
        $response = $this->withHeader('X-Customer-Session', $token)
            ->getJson('/api/v1/customer/menu');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data'); // Hanya kategori Kopi A
        $response->assertJsonPath('data.0.name', 'Kopi A');
        $response->assertJsonPath('data.0.menus.0.name', 'Kopi Susu A');

        // Pastikan menu Resto B tidak bocor masuk ke respons
        $this->assertFalse(collect($response->json('data.0.menus'))->contains('name', 'Kopi Hitam B'));
    }
}
