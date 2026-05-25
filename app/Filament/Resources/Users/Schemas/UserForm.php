<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                
                Repeater::make('memberships')
                    ->relationship('memberships')
                    ->schema([
                        Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->required(),
                        Select::make('role')
                            ->options([
                                'owner' => 'Owner',
                                'cashier' => 'Cashier',
                                'kitchen' => 'Kitchen',
                            ])
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
