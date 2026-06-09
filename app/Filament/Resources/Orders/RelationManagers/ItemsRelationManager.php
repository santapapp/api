<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\BillStatus;
use App\Enums\ItemStatus;
use App\Enums\MenuType;
use App\Enums\PaymentStatus;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderItemService;
use App\Services\OrderQrisPaymentService;
use App\Services\QrisService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->description(fn (OrderItem $record): ?string => $this->optionSummary($record))
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
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->emptyStateHeading('Belum ada item pesanan')
            ->emptyStateDescription(function (): string {
                /** @var Order $order */
                $order = $this->getOwnerRecord();
                return $order->isCancelled() || $order->cancelled_at !== null
                    ? 'Tidak ada item pada sesi yang dibatalkan ini.'
                    : 'Customer atau kasir belum menambahkan item ke sesi ini.';
            })
            ->filters([])
            ->headerActions([
                Action::make('add_menu')
                    ->label('Tambah Menu')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn (): bool => $this->canMutateOwnerItems())
                    ->schema([
                        Select::make('menu_id')
                            ->label('Menu')
                            ->options(fn (): array => $this->productOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        TextInput::make('quantity')
                            ->label('Qty')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(99)
                            ->default(1)
                            ->required(),
                        Repeater::make('selected_options')
                            ->label('Variant / Addon')
                            ->schema([
                                Select::make('group_id')
                                    ->label('Group')
                                    ->options(fn (Get $get): array => $this->optionGroupOptions((int) ($get('../../menu_id') ?? 0)))
                                    ->searchable()
                                    ->live()
                                    ->required(),
                                Select::make('option_id')
                                    ->label('Option')
                                    ->options(fn (Get $get): array => $this->optionOptions((int) ($get('group_id') ?? 0)))
                                    ->searchable()
                                    ->required(),
                            ])
                            ->default([])
                            ->columns(2),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->maxLength(500),
                    ])
                    ->action(function (array $data): void {
                        /** @var Order $order */
                        $order = $this->getOwnerRecord();
                        $order = app(OrderQrisPaymentService::class)->ensureItemsMutable($order, app(QrisService::class));

                        app(OrderItemService::class)->addItems($order, [[
                            'menu_id' => (int) $data['menu_id'],
                            'quantity' => (int) $data['quantity'],
                            'note' => $data['note'] ?? null,
                            'selected_options' => $data['selected_options'] ?? [],
                        ]]);

                        Notification::make()
                            ->title('Menu ditambahkan')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('update_quantity')
                    ->label('Ubah Qty')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (OrderItem $record): bool => $this->canMutateOwnerItems()
                        && $record->item_status === ItemStatus::Pending)
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Qty')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(99)
                            ->required(),
                    ])
                    ->fillForm(fn (OrderItem $record): array => ['quantity' => $record->quantity])
                    ->action(function (array $data, OrderItem $record): void {
                        /** @var Order $order */
                        $order = $this->getOwnerRecord();
                        $order = app(OrderQrisPaymentService::class)->ensureItemsMutable($order, app(QrisService::class));

                        app(OrderItemService::class)->updateQuantity($order, $record, (int) $data['quantity']);

                        Notification::make()
                            ->title('Quantity diperbarui')
                            ->success()
                            ->send();
                    }),
                Action::make('delete_item')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OrderItem $record): bool => $this->canMutateOwnerItems()
                        && $record->item_status === ItemStatus::Pending)
                    ->action(function (OrderItem $record): void {
                        /** @var Order $order */
                        $order = $this->getOwnerRecord();
                        $order = app(OrderQrisPaymentService::class)->ensureItemsMutable($order, app(QrisService::class));

                        app(OrderItemService::class)->removeItem($order, $record);

                        Notification::make()
                            ->title('Item dihapus')
                            ->success()
                            ->send();
                    }),
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
                        $record->order->syncStatusFromItems();

                        Notification::make()
                            ->title('Status item diperbarui')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    private function canMutateOwnerItems(): bool
    {
        /** @var Order $order */
        $order = $this->getOwnerRecord();
        $payments = app(OrderQrisPaymentService::class);

        return $order->payment_status !== PaymentStatus::Paid
            && ($order->payment_status !== PaymentStatus::Pending || $payments->isLocallyExpiredPending($order))
            && $order->bill_status !== BillStatus::Closed
            && $order->order_status->value !== 'cancelled';
    }

    private function productOptions(): array
    {
        /** @var Order $order */
        $order = $this->getOwnerRecord();

        return Menu::query()
            ->where('organization_id', $order->organization_id)
            ->whereNull('parent_id')
            ->where('type', MenuType::Product)
            ->where('is_available', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function optionGroupOptions(int $menuId): array
    {
        if ($menuId <= 0) {
            return [];
        }

        /** @var Order $order */
        $order = $this->getOwnerRecord();

        return Menu::query()
            ->where('organization_id', $order->organization_id)
            ->where('parent_id', $menuId)
            ->whereIn('type', [MenuType::VariantGroup, MenuType::AddonGroup])
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    private function optionOptions(int $groupId): array
    {
        if ($groupId <= 0) {
            return [];
        }

        /** @var Order $order */
        $order = $this->getOwnerRecord();

        return Menu::query()
            ->where('organization_id', $order->organization_id)
            ->where('parent_id', $groupId)
            ->whereIn('type', [MenuType::Variant, MenuType::Addon])
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    private function optionSummary(OrderItem $record): ?string
    {
        $options = $record->metadata['selected_options'] ?? [];

        if (! is_array($options) || empty($options)) {
            return $record->parent_item_id ? 'tambahan' : null;
        }

        return collect($options)
            ->map(fn (array $option): string => ($option['group_name'] ?? 'Option') . ': ' . ($option['option_name'] ?? '-'))
            ->join(', ');
    }
}
