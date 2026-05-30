<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentOrders extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'Pesanan Terbaru';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with('organization')
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('ID Pesanan')
                    ->searchable()
                    ->weight('medium')
                    ->copyable(),
                
                TextColumn::make('organization.name')
                    ->label('Mitra')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'qris' => 'info',
                        'cash' => 'success',
                        'transfer' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed', 'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => ucfirst((string) ($state?->value ?? $state))),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
