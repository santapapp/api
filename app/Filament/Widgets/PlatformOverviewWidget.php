<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrganizationStatus;
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
                ->description('Active, inactive, and suspended')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),
            Stat::make('Active Organizations', Organization::where('status', OrganizationStatus::Active)->count())
                ->description('Currently active organizations')
                ->color('success'),
            Stat::make('Suspended Organizations', Organization::where('status', OrganizationStatus::Suspended)->count())
                ->description('Suspended organizations')
                ->color('danger'),
        ];
    }
}
