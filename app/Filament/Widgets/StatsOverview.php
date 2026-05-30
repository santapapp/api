<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $todayRevenue = Order::where('created_at', '>=', $today)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $todayOrders = Order::where('created_at', '>=', $today)->count();
        $pendingQris = Order::where('payment_method', 'qris')->where('payment_status', 'pending')->count();
        $totalTenants = Organization::count();

        // Dummy sparkline data untuk mempercantik UI
        // Di aplikasi riil, ini bisa di-query dari data per-jam
        $revenueTrend = [7, 10, 13, 15, 14, 18, 22]; 
        $orderTrend   = [5, 8, 12, 10, 15, 20, 25];

        return [
            Stat::make('Pendapatan Hari Ini', 'Rp ' . number_format((float) $todayRevenue, 0, ',', '.'))
                ->description('Total dari semua mitra')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart($revenueTrend),

            Stat::make('Pesanan Hari Ini', $todayOrders)
                ->description('Jumlah transaksi hari ini')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info')
                ->chart($orderTrend),

            Stat::make('QRIS Menunggu', $pendingQris)
                ->description('Butuh konfirmasi pembayaran')
                ->descriptionIcon($pendingQris > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($pendingQris > 0 ? 'warning' : 'success'),

            Stat::make('Total Mitra', $totalTenants)
                ->description(Organization::where('is_active', true)->count() . ' Mitra Aktif')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),
        ];
    }
}
