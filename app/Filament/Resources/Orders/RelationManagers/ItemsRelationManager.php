<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'allItems';

    protected static ?string $title = 'Order Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only view, no editing fields needed
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_price')
                    ->label('Base Price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('variant_total')
                    ->label('Variant +')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->sortable(),
                TextColumn::make('subtotal')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('item_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('note')
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
