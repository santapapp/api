<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Enums\MenuType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('type')
                    ->options(\App\Enums\MenuType::class)
                    ->required()
                    ->live(),
                Select::make('parent_id')
                    ->relationship('parent', 'name', function (\Illuminate\Database\Eloquent\Builder $query, $get) {
                        $type = $get('type');
                        if (in_array($type, [\App\Enums\MenuType::VariantGroup->value, \App\Enums\MenuType::AddonGroup->value])) {
                            $query->where('type', \App\Enums\MenuType::Product);
                        } elseif ($type === \App\Enums\MenuType::Variant->value) {
                            $query->where('type', \App\Enums\MenuType::VariantGroup);
                        } elseif ($type === \App\Enums\MenuType::Addon->value) {
                            $query->where('type', \App\Enums\MenuType::AddonGroup);
                        } else {
                            $query->where('type', '!=', \App\Enums\MenuType::Variant)->where('type', '!=', \App\Enums\MenuType::Addon);
                        }
                        
                        $orgId = $get('organization_id');
                        if ($orgId) {
                            $query->where('organization_id', $orgId);
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->label('Parent Menu')
                    ->hidden(fn ($get) => $get('type') === \App\Enums\MenuType::Product->value),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('metadata.category')
                    ->label('Category')
                    ->placeholder('e.g., Makanan, Minuman')
                    ->visible(fn ($get) => $get('type') === \App\Enums\MenuType::Product->value),
                FileUpload::make('image')
                    ->label('Gambar')
                    ->image()
                    ->disk('public')
                    ->directory('menu/images')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->imagePreviewHeight('100')
                    ->helperText('JPG/PNG/WEBP, maks 2 MB.'),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('IDR')
                    ->visible(fn ($get) => in_array($get('type'), [
                        \App\Enums\MenuType::Product->value,
                        \App\Enums\MenuType::Variant->value,
                        \App\Enums\MenuType::Addon->value
                    ])),
                TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(255)
                    ->visible(fn ($get) => in_array($get('type'), [
                        \App\Enums\MenuType::Product->value,
                        \App\Enums\MenuType::Variant->value,
                        \App\Enums\MenuType::Addon->value
                    ])),
                TextInput::make('min_select')
                    ->numeric()
                    ->default(0)
                    ->visible(fn ($get) => in_array($get('type'), [
                        \App\Enums\MenuType::VariantGroup->value,
                        \App\Enums\MenuType::AddonGroup->value
                    ])),
                TextInput::make('max_select')
                    ->numeric()
                    ->default(1)
                    ->visible(fn ($get) => in_array($get('type'), [
                        \App\Enums\MenuType::VariantGroup->value,
                        \App\Enums\MenuType::AddonGroup->value
                    ])),
                Toggle::make('is_required')
                    ->default(false)
                    ->visible(fn ($get) => in_array($get('type'), [
                        \App\Enums\MenuType::VariantGroup->value,
                        \App\Enums\MenuType::AddonGroup->value
                    ])),
                Toggle::make('is_available')
                    ->default(true)
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
