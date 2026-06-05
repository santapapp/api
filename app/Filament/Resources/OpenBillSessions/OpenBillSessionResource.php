<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpenBillSessions;

use App\Enums\BillStatus;
use App\Enums\OrderType;
use App\Filament\Resources\OpenBillSessions\Pages\ListOpenBillSessions;
use App\Filament\Resources\OpenBillSessions\Tables\OpenBillSessionsTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
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
 * Read-only: pembuatan open bill hanya lewat API cashier, bukan dari dashboard.
 */
class OpenBillSessionResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'Open Bill';

    protected static ?string $pluralModelLabel = 'Open Bill Aktif';

    protected static ?string $navigationLabel = 'Open Bill Aktif';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 7;

    /**
     * Base query: HANYA open bill yang masih terbuka.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('order_type', OrderType::OpenBill)
            ->where('bill_status', BillStatus::Open);
    }

    public static function table(Table $table): Table
    {
        return OpenBillSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpenBillSessions::route('/'),
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
