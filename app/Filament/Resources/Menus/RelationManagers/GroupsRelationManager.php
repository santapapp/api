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

class GroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Variant & Addon Groups';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('organization_id')
                    ->default(fn ($livewire) => $livewire->getOwnerRecord()->organization_id),
                Select::make('type')
                    ->options([
                        MenuType::VariantGroup->value => 'Variant Group',
                        MenuType::AddonGroup->value => 'Addon Group',
                    ])
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_required')
                    ->label('Required?'),
                TextInput::make('min_select')
                    ->numeric()
                    ->default(0),
                TextInput::make('max_select')
                    ->numeric()
                    ->default(1),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->groups())
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state->value ?? $state) {
                        'variant_group' => 'Variant Group',
                        'addon_group' => 'Addon Group',
                        default => $state->value ?? $state,
                    })
                    ->color(fn ($state) => match($state->value ?? $state) {
                        'variant_group' => 'info',
                        'addon_group' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('is_required')->boolean(),
                TextColumn::make('min_select')->label('Min'),
                TextColumn::make('max_select')->label('Max'),
                IconColumn::make('is_available')->boolean(),
                TextColumn::make('sort_order')->sortable(),
                TextColumn::make('children_count')
                    ->counts('children')
                    ->label('Items'),
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
                // Custom Action to manage items inside the group
                \Filament\Actions\Action::make('manage_items')
                    ->label('Manage Items')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn (Model $record): string => \App\Filament\Resources\Menus\MenuResource::getUrl('edit', ['record' => $record]))
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
