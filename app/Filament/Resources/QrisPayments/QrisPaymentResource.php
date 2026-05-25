<?php

namespace App\Filament\Resources\QrisPayments;

use App\Filament\Resources\QrisPayments\Pages\CreateQrisPayment;
use App\Filament\Resources\QrisPayments\Pages\EditQrisPayment;
use App\Filament\Resources\QrisPayments\Pages\ListQrisPayments;
use App\Filament\Resources\QrisPayments\Schemas\QrisPaymentForm;
use App\Filament\Resources\QrisPayments\Tables\QrisPaymentsTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QrisPaymentResource extends Resource
{
    protected static ?string $model = Order::class;
    
    protected static ?string $modelLabel = 'QRIS Payment';
    protected static ?string $pluralModelLabel = 'QRIS Payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('payment_reference')
            ->where('payment_method', 'qris');
    }

    public static function form(Schema $schema): Schema
    {
        return QrisPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QrisPaymentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQrisPayments::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model|Order $record): bool
    {
        return false;
    }
}
