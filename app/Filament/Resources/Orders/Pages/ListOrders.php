<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'waiting_payment' => Tab::make('Menunggu Bayar')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('payment_status', PaymentStatus::Pending)),
            'processing' => Tab::make('Diproses')
                ->icon('heroicon-o-fire')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('order_status', [OrderStatus::Confirmed, OrderStatus::Preparing])),
            'ready' => Tab::make('Siap')
                ->icon('heroicon-o-bell-alert')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('order_status', OrderStatus::Ready)),
            'completed' => Tab::make('Selesai')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('order_status', OrderStatus::Completed)),
            'cancelled' => Tab::make('Dibatalkan')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('order_status', OrderStatus::Cancelled)),
        ];
    }
}
