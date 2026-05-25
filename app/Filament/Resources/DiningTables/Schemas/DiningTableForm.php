<?php

namespace App\Filament\Resources\DiningTables\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DiningTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                TextInput::make('qr_token')
                    ->default(fn () => Str::random(32))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->readOnly()
                    ->helperText('This token will be regenerated automatically if you click the Regenerate action.'),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
