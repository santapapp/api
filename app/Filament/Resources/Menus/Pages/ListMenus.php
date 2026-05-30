<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Filament\Resources\Menus\MenuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'product';
    }

    public function getTabs(): array
    {
        return [
            'all' => \Filament\Schemas\Components\Tabs\Tab::make('All Menus'),
            'product' => \Filament\Schemas\Components\Tabs\Tab::make('Products')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('type', \App\Enums\MenuType::Product)),
            'variant_group' => \Filament\Schemas\Components\Tabs\Tab::make('Variant Groups')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('type', \App\Enums\MenuType::VariantGroup)),
            'variant' => \Filament\Schemas\Components\Tabs\Tab::make('Variants')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('type', \App\Enums\MenuType::Variant)),
            'addon_group' => \Filament\Schemas\Components\Tabs\Tab::make('Addon Groups')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('type', \App\Enums\MenuType::AddonGroup)),
            'addon' => \Filament\Schemas\Components\Tabs\Tab::make('Addons')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('type', \App\Enums\MenuType::Addon)),
        ];
    }
}
