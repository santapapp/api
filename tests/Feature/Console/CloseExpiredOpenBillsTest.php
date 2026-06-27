<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CloseExpiredOpenBillsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private DiningTable $table;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'      => 'Test Resto',
            'slug'      => 'test-resto',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();

        $this->table = DiningTable::create([
            'organization_id' => $this->org->id,
            'name'            => 'Meja 1',
            'code'            => 'T1',
            'qr_token'        => Str::random(32),
            'is_active'       => true,
        ]);
    }

    public function test_closes_expired_open_bills(): void
    {
        // 1. Sesi open bill yang baru dibuat (< 24 jam)
        $newOrder = Order::create([
            'order_number'                 => 'ORD-NEW',
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->user->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Open,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 10000,
            'opened_at'                    => now()->subHours(23), // 23 jam yang lalu
        ]);

        // 2. Sesi open bill yang sudah lama dibuat (>= 24 jam)
        $expiredOrder = Order::create([
            'order_number'                 => 'ORD-EXPIRED',
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->user->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Open,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 10000,
            'opened_at'                    => now()->subHours(25), // 25 jam yang lalu
        ]);

        // 3. Sesi order biasa (bukan open_bill) yang sudah > 24 jam (tidak boleh tersentuh)
        $tableOrder = Order::create([
            'order_number'                 => 'ORD-TABLE',
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->user->id,
            'order_type'                   => OrderType::TableOrder,
            'bill_status'                  => BillStatus::None,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 10000,
            'opened_at'                    => now()->subHours(25),
        ]);

        // Jalankan console command
        $this->artisan('orders:close-expired-open-bills')
            ->expectsOutput('Menemukan 1 sesi open bill aktif yang kedaluwarsa.')
            ->expectsOutput('Berhasil menutup 1 sesi open bill kedaluwarsa.')
            ->assertExitCode(0);

        // Verifikasi hasil database
        $this->assertSame(BillStatus::Open, $newOrder->fresh()->bill_status);
        $this->assertNull($newOrder->fresh()->closed_at);

        $this->assertSame(BillStatus::Closed, $expiredOrder->fresh()->bill_status);
        $this->assertNotNull($expiredOrder->fresh()->closed_at);

        $this->assertSame(BillStatus::None, $tableOrder->fresh()->bill_status);
    }

    public function test_dry_run_does_not_modify_database(): void
    {
        $expiredOrder = Order::create([
            'order_number'                 => 'ORD-EXPIRED-DRY',
            'public_token'                 => Str::random(32),
            'organization_id'              => $this->org->id,
            'dining_table_id'              => $this->table->id,
            'created_by'                   => $this->user->id,
            'order_type'                   => OrderType::OpenBill,
            'bill_status'                  => BillStatus::Open,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => 0,
            'service_charge_rate_snapshot' => 0,
            'total_amount'                 => 10000,
            'opened_at'                    => now()->subHours(25), // 25 jam yang lalu
        ]);

        $this->artisan('orders:close-expired-open-bills --dry-run')
            ->expectsOutput('Menemukan 1 sesi open bill aktif yang kedaluwarsa.')
            ->expectsOutput('Mode dry-run aktif. Tidak ada perubahan yang dilakukan pada database.')
            ->assertExitCode(0);

        // Verifikasi database tidak berubah
        $this->assertSame(BillStatus::Open, $expiredOrder->fresh()->bill_status);
        $this->assertNull($expiredOrder->fresh()->closed_at);
    }
}
