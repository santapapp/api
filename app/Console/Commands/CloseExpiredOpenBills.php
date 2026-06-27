<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BillStatus;
use App\Enums\OrderType;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseExpiredOpenBills extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:close-expired-open-bills {--dry-run : Hanya tampilkan data open bill kedaluwarsa tanpa mengupdate database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menutup sesi open bill yang aktif lebih dari 24 jam';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $expirationTime = now()->subHours(24);

        $query = Order::query()
            ->where('order_type', OrderType::OpenBill)
            ->where('bill_status', BillStatus::Open)
            ->where('opened_at', '<=', $expirationTime);

        $count = $query->count();

        if ($count === 0) {
            $this->info('Tidak ditemukan sesi open bill aktif yang kedaluwarsa (> 24 jam).');
            return;
        }

        $this->info("Menemukan {$count} sesi open bill aktif yang kedaluwarsa.");

        if ($this->option('dry-run')) {
            $this->info('Mode dry-run aktif. Tidak ada perubahan yang dilakukan pada database.');
            foreach ($query->get() as $order) {
                $this->line("- ID: {$order->id}, No Order: {$order->order_number}, Dibuka pada: {$order->opened_at}");
            }
            return;
        }

        $affected = 0;
        foreach ($query->get() as $order) {
            $order->update([
                'bill_status' => BillStatus::Closed,
                'closed_at'   => now(),
            ]);

            Log::info('CloseExpiredOpenBills: Sesi open bill otomatis ditutup karena melebihi 24 jam', [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'opened_at'    => $order->opened_at?->toIso8601String(),
                'closed_at'    => now()->toIso8601String(),
            ]);

            $affected++;
        }

        $this->info("Berhasil menutup {$affected} sesi open bill kedaluwarsa.");
    }
}
