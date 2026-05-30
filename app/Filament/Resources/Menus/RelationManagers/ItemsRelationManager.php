<?php

namespace App\Filament\Resources\Menus\RelationManagers;

use App\Enums\MenuType;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Variants & Addons';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('organization_id')
                    ->default(fn ($livewire) => $livewire->getOwnerRecord()->organization_id),
                Select::make('type')
                    ->options(function ($livewire) {
                        $parentType = $livewire->getOwnerRecord()->type->value ?? $livewire->getOwnerRecord()->type;
                        if ($parentType === MenuType::VariantGroup->value) {
                            return [MenuType::Variant->value => 'Variant'];
                        }
                        if ($parentType === MenuType::AddonGroup->value) {
                            return [MenuType::Addon->value => 'Addon'];
                        }
                        return [
                            MenuType::Variant->value => 'Variant',
                            MenuType::Addon->value => 'Addon',
                        ];
                    })
                    ->default(function ($livewire) {
                        $parentType = $livewire->getOwnerRecord()->type->value ?? $livewire->getOwnerRecord()->type;
                        if ($parentType === MenuType::VariantGroup->value) {
                            return MenuType::Variant->value;
                        }
                        if ($parentType === MenuType::AddonGroup->value) {
                            return MenuType::Addon->value;
                        }
                        return null;
                    })
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('price')
                    ->numeric()
                    ->default(0)
                    ->prefix('IDR'),
                TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_available')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->items())
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state->value ?? $state))
                    ->color(fn ($state) => match($state->value ?? $state) {
                        'variant' => 'info',
                        'addon' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('price')->money('IDR'),
                TextColumn::make('sku')->label('SKU')->placeholder('-'),
                IconColumn::make('is_available')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['parent_id'] = $this->getOwnerRecord()->id;
                        $data['organization_id'] = $this->getOwnerRecord()->organization_id;
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
