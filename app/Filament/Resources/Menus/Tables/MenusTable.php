<?php

namespace App\Filament\Resources\Menus\Tables;

use App\Enums\MenuType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Gambar')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(asset('images/menu-placeholder.svg')),
                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('metadata.category')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state->value ?? $state) {
                        'product' => 'Product',
                        'variant_group' => 'Variant Group',
                        'variant' => 'Variant',
                        'addon_group' => 'Addon Group',
                        'addon' => 'Addon',
                        default => $state->value ?? $state,
                    })
                    ->color(fn ($state) => match($state->value ?? $state) {
                        'product' => 'success',
                        'variant_group', 'variant' => 'info',
                        'addon_group', 'addon' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('None')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('children_count')
                    ->counts('children')
                    ->label('Options/Items')
                    ->sortable(),
                IconColumn::make('is_available')
                    ->label('Available')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->label('Organization')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->options(MenuType::class),
                SelectFilter::make('category')
                    ->label('Category')
                    ->options(function () {
                        // Ambil sebagai kolom scalar beralias `category` lalu pluck.
                        // pluck('metadata->category') tidak boleh dipakai: Eloquent
                        // menafsirkannya sebagai nested property ($row->metadata->category)
                        // → "Undefined property: stdClass::$metadata" di PostgreSQL.
                        return \App\Models\Menu::query()
                            ->whereNotNull('metadata->category')
                            ->selectRaw("metadata->>'category' as category")
                            ->distinct()
                            ->pluck('category', 'category')
                            ->toArray();
                    })
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->where('metadata->category', $data['value']);
                        }
                    }),
                TernaryFilter::make('is_available')
                    ->label('Available'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
