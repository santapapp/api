<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = now()->startOfDay();

        return [
            Stat::make('Total Organizations', \App\Models\Organization::count())
                ->description('Platform tenants')
                ->icon('heroicon-o-building-storefront'),
            Stat::make('Active Organizations', \App\Models\Organization::where('is_active', 'true')->count())
                ->description('Currently active')
                ->color('success')
                ->icon('heroicon-o-check-badge'),
            Stat::make('Total Users', \App\Models\User::count())
                ->description('Registered accounts')
                ->icon('heroicon-o-users'),
            Stat::make("Today's Orders", \App\Models\Order::where('created_at', '>=', $today)->count())
                ->description('All tenant orders today')
                ->icon('heroicon-o-shopping-bag'),
            Stat::make("Today's Paid Orders", \App\Models\Order::where('created_at', '>=', $today)->where('payment_status', 'paid')->count())
                ->description('Successfully paid')
                ->color('success')
                ->icon('heroicon-o-currency-dollar'),
            Stat::make('Pending QRIS', \App\Models\Order::where('payment_method', 'qris')->where('payment_status', 'pending')->count())
                ->description('Needs attention')
                ->color('warning')
                ->icon('heroicon-o-qr-code'),
            Stat::make("Today's Revenue", 'Rp ' . number_format(\App\Models\Order::where('created_at', '>=', $today)->where('payment_status', 'paid')->sum('total_amount'), 0, ',', '.'))
                ->description('Total across platform')
                ->color('success')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
