<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')
                    ->required(),
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
                Select::make('dining_table_id')
                    ->relationship('diningTable', 'name'),
                TextInput::make('created_by')
                    ->numeric(),
                Select::make('order_type')
                    ->options(OrderType::class)
                    ->required(),
                Select::make('bill_status')
                    ->options(BillStatus::class)
                    ->required(),
                Select::make('order_status')
                    ->options(OrderStatus::class)
                    ->required(),
                Select::make('payment_status')
                    ->options(PaymentStatus::class)
                    ->required(),
                TextInput::make('payment_method'),
                TextInput::make('payment_reference'),
                TextInput::make('payment_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('subtotal_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('note')
                    ->columnSpanFull(),
                DateTimePicker::make('paid_at'),
                DateTimePicker::make('opened_at'),
                DateTimePicker::make('closed_at'),
            ]);
    }
}
