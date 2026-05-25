<?php

namespace App\Filament\Resources\Organizations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('owners.name')
                    ->label('Owner')
                    ->badge(),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Total Users'),
                TextColumn::make('dining_tables_count')
                    ->counts('diningTables')
                    ->label('Total Dining Tables'),
                TextColumn::make('menus_count')
                    ->counts('menus')
                    ->label('Total Menus'),
                TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label('Total Orders'),
                ToggleColumn::make('is_active')
                    ->label('Status'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
