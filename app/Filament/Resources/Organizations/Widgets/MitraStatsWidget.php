<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Widgets;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MitraStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalOrg       = Organization::count();
        $activeOrg      = Organization::where('is_active', true)->count();
        $inactiveOrg    = Organization::where('is_active', false)->count();

        $totalMembers   = OrganizationMember::count();
        $totalOwners    = OrganizationMember::where('role', 'owner')->count();
        $totalCashiers  = OrganizationMember::where('role', 'cashier')->count();
        $totalKitchen   = OrganizationMember::where('role', 'kitchen')->count();

        return [
            Stat::make('Total Mitra', $totalOrg)
                ->description('Semua mitra terdaftar')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),

            Stat::make('Mitra Aktif', $activeOrg)
                ->description("{$inactiveOrg} nonaktif")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Total User Mitra', $totalMembers)
                ->description('Seluruh anggota di semua mitra')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Owner', $totalOwners)
                ->description('Pemilik restoran')
                ->descriptionIcon('heroicon-m-star')
                ->color('danger'),

            Stat::make('Cashier', $totalCashiers)
                ->description('Staff kasir')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Kitchen', $totalKitchen)
                ->description('Staff dapur')
                ->descriptionIcon('heroicon-m-fire')
                ->color('amber' ),
        ];
    }
}
