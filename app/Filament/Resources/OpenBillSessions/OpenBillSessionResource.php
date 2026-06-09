<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpenBillSessions;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Filament\Resources\OpenBillSessions\Pages\ListOpenBillSessions;
use App\Filament\Resources\OpenBillSessions\Pages\ViewOpenBillSession;
use App\Filament\Resources\OpenBillSessions\Tables\OpenBillSessionsTable;
use App\Filament\Resources\Orders\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Virtual resource (model Order) untuk memantau Open Bill Session yang masih AKTIF.
 *
 * "Aktif" = order_type=open_bill AND bill_status=open — definisi yang sama dengan
 * middleware EnsureCustomerToken (sumber kebenaran untuk join customer). Order yang
 * sudah closed/paid/expired/cancelled otomatis keluar dari daftar karena bill_status
 * tidak lagi `open`.
 *
 * Resource ini tetap list-only; pembuatan open bill diarahkan ke halaman create
 * Orders agar default status/snapshot rate konsisten dengan order kasir.
 */
class OpenBillSessionResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'Open Bill';

    protected static ?string $pluralModelLabel = 'Open Bill Aktif';

    protected static ?string $navigationLabel = 'Open Bill Aktif';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static \UnitEnum|string|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 7;

    /**
     * Base query: HANYA open bill yang masih terbuka dan tidak dibatalkan.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('order_type', OrderType::OpenBill)
            ->where('bill_status', BillStatus::Open)
            ->where('order_status', '!=', OrderStatus::Cancelled)
            ->whereNull('cancelled_at')
            ->whereNull('closed_at');
    }

    public static function table(Table $table): Table
    {
        return OpenBillSessionsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpenBillSessions::route('/'),
            'view'  => ViewOpenBillSession::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model|Order $record): bool
    {
        return false;
    }

    public static function canDelete(Model|Order $record): bool
    {
        return false;
    }
}
