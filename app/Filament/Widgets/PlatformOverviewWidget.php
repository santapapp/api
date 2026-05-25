<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Organization;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('Total registered users on the platform')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
            Stat::make('Total Organizations', Organization::count())
                ->description('Active and inactive organizations')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),
            Stat::make('Active Organizations', Organization::where('is_active', 'true')->count())
                ->description('Currently active organizations')
                ->color('success'),
            Stat::make('Inactive Organizations', Organization::where('is_active', 'false')->count())
                ->description('Inactive organizations')
                ->color('danger'),
        ];
    }
}
