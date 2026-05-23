<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\MemberStatus;
use App\Filament\Resources\OrganizationMemberResource\Pages;
use App\Models\OrganizationMember;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\PermissionRegistrar;

class OrganizationMemberResource extends Resource
{
    protected static ?string $model = OrganizationMember::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-identification';

    protected static \UnitEnum|string|null $navigationGroup = 'Platform Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\TextInput::make('role_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'invited' => 'Invited',
                        'suspended' => 'Suspended',
                        'left' => 'Left',
                    ])
                    ->required()
                    ->default('active'),
                Forms\Components\DateTimePicker::make('joined_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (MemberStatus $state): string => match ($state) {
                        MemberStatus::Active => 'success',
                        MemberStatus::Invited => 'warning',
                        MemberStatus::Suspended => 'danger',
                        MemberStatus::Left => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('joined_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'invited' => 'Invited',
                        'suspended' => 'Suspended',
                        'left' => 'Left',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('force_remove')
                    ->label('Force Remove')
                    ->action(function (OrganizationMember $record) {
                        $user = $record->user;
                        $orgId = $record->organization_id;

                        // Revoke Spatie role for this tenant
                        app(PermissionRegistrar::class)->setPermissionsTeamId($orgId);
                        $user->removeRole($record->role_name);

                        // Delete membership
                        $record->delete();
                    })
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-user-minus'),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOrganizationMembers::route('/'),
        ];
    }
}
