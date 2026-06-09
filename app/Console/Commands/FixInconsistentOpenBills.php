<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use Illuminate\Console\Command;

class FixInconsistentOpenBills extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:fix-inconsistent-open-bills {--dry-run : Hanya tampilkan data yang tidak konsisten tanpa mengupdate database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memperbaiki open bill tidak konsisten (order status cancelled tapi bill status masih open)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $query = Order::query()
            ->where('order_type', OrderType::OpenBill)
            ->where('order_status', OrderStatus::Cancelled)
            ->where('bill_status', BillStatus::Open);

        $count = $query->count();

        if ($count === 0) {
            $this->info('Tidak ditemukan data open bill yang tidak konsisten.');
            return;
        }

        $this->info("Menemukan {$count} data open bill yang tidak konsisten (Order Dibatalkan tapi Bill Terbuka).");

        if ($this->option('dry-run')) {
            $this->info('Mode dry-run aktif. Tidak ada perubahan yang dilakukan pada database.');
            foreach ($query->get() as $order) {
                $this->line("- ID: {$order->id}, No Order: {$order->order_number}, Dibatalkan pada: {$order->cancelled_at}");
            }
            return;
        }

        if ($this->confirm('Apakah Anda yakin ingin memperbaiki data ini? Status bill akan diubah menjadi Closed.')) {
            $affected = 0;
            foreach ($query->get() as $order) {
                $order->update([
                    'bill_status' => BillStatus::Closed,
                    'closed_at'   => $order->cancelled_at ?? $order->updated_at ?? now(),
                ]);
                $affected++;
            }
            $this->info("Berhasil memperbaiki {$affected} data open bill.");
        }
    }
}
