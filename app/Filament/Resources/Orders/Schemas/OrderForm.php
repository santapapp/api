<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderType;
use App\Models\DiningTable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_type')
                    ->label('Tipe Order')
                    ->options([
                        OrderType::CashierOrder->value => OrderType::CashierOrder->getLabel(),
                        OrderType::OpenBill->value     => OrderType::OpenBill->getLabel(),
                    ])
                    ->default(self::defaultOrderType())
                    ->required()
                    ->live(),

                Select::make('organization_id')
                    ->label('Restoran')
                    ->relationship(
                        'organization',
                        'name',
                        fn (Builder $query): Builder => $query->where('is_active', true),
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('dining_table_id', null)),

                Select::make('dining_table_id')
                    ->label('Meja')
                    ->options(function (Get $get): array {
                        $orgId = $get('organization_id');

                        if (! $orgId) {
                            return [];
                        }

                        return DiningTable::query()
                            ->where('organization_id', $orgId)
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (DiningTable $table): array => [
                                $table->id => $table->code
                                    ? "{$table->name} ({$table->code})"
                                    : $table->name,
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => blank($get('organization_id')))
                    ->helperText('Opsional. Untuk Open Bill, pilih meja bila sesi perlu ditautkan ke meja.'),

                TextInput::make('customer_name')
                    ->label('Nama Pelanggan')
                    ->maxLength(100),

                TextInput::make('customer_phone')
                    ->label('No. HP Pelanggan')
                    ->tel()
                    ->maxLength(20),

                Textarea::make('note')
                    ->label('Catatan')
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    private static function defaultOrderType(): string
    {
        $requested = request()->query('order_type');

        return in_array($requested, [OrderType::CashierOrder->value, OrderType::OpenBill->value], true)
            ? $requested
            : OrderType::CashierOrder->value;
    }
}
