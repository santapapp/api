<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\ItemStatus;
use App\Models\OrderItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'allItems';

    protected static ?string $title = 'Item Pesanan';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('menu')->orderBy('parent_item_id'))
            ->columns([
                ImageColumn::make('menu.image')
                    ->label('')
                    ->disk('public')
                    ->visibility('public')
                    ->imageHeight(44)
                    ->square()
                    ->defaultImageUrl(asset('images/menu-placeholder.svg')),
                TextColumn::make('name')
                    ->label('Item')
                    ->description(fn ($record): ?string => $record->parent_item_id ? '↳ tambahan' : null)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('item_type')
                    ->label('Tipe')
                    ->badge(),
                TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('item_status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([
                Action::make('set_item_status')
                    ->label('Ubah Status')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('gray')
                    ->schema([
                        Select::make('item_status')
                            ->label('Status Item')
                            ->options(ItemStatus::class)
                            ->required(),
                    ])
                    ->fillForm(fn (OrderItem $record): array => [
                        'item_status' => $record->item_status?->value,
                    ])
                    ->action(function (array $data, OrderItem $record): void {
                        $record->update(['item_status' => $data['item_status']]);

                        // Rollup: order_status menyesuaikan agregat item (item-driven).
                        $record->order->syncStatusFromItems();

                        Notification::make()
                            ->title('Status item diperbarui')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
