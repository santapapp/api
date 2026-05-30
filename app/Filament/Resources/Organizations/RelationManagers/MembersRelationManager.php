<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Models\OrganizationMember;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MembersRelationManager extends RelationManager
{
    /**
     * Relationship ke OrganizationMember (HasMany via memberRecords)
     */
    protected static string $relationship = 'memberRecords';

    protected static ?string $title = 'Anggota Mitra';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-users';

    // ──────────────────────────────────────────────────────────────
    // Form (tidak dipakai langsung — kita pakai custom Action forms)
    // ──────────────────────────────────────────────────────────────
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')
                ->label('Role di Mitra Ini')
                ->options([
                    'owner'   => '👑 Owner',
                    'cashier' => '🧾 Cashier',
                    'kitchen' => '🍳 Kitchen',
                ])
                ->required()
                ->default('cashier')
                ->columnSpanFull(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Table daftar anggota
    // ──────────────────────────────────────────────────────────────
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->color('gray'),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'owner'   => 'danger',
                        'cashier' => 'success',
                        'kitchen' => 'warning',
                        default   => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owner'   => '👑 Owner',
                        'cashier' => '🧾 Cashier',
                        'kitchen' => '🍳 Kitchen',
                        default   => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('user.status')
                    ->label('Status User')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'active'    => 'success',
                        'inactive'  => 'gray',
                        'suspended' => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => ucfirst((string) ($state?->value ?? $state))),

                TextColumn::make('created_at')
                    ->label('Bergabung')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'owner'   => 'Owner',
                        'cashier' => 'Cashier',
                        'kitchen' => 'Kitchen',
                    ]),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('tambah_anggota')
                    ->label('Tambah Anggota')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Select::make('user_id')
                            ->label('Pilih User yang Sudah Ada')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search, RelationManager $livewire) {
                                $orgId = $livewire->getOwnerRecord()->id;

                                return User::query()
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                          ->orWhere('email', 'like', "%{$search}%");
                                    })
                                    ->whereNotIn('id', function ($q) use ($orgId) {
                                        $q->select('user_id')
                                          ->from('organization_members')
                                          ->where('organization_id', $orgId);
                                    })
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [
                                        $user->id => "{$user->name} ({$user->email})",
                                    ])
                                    ->toArray();
                            })
                            ->helperText('Cari berdasarkan nama atau email. Kosongkan jika ingin buat user baru.')
                            ->columnSpanFull(),

                        Fieldset::make('Atau Buat User Baru')
                            ->schema([
                                TextInput::make('new_user_name')
                                    ->label('Nama Lengkap')
                                    ->maxLength(255),

                                TextInput::make('new_user_email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('new_user_password')
                                    ->label('Password')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Kosongkan untuk generate otomatis')
                                    ->minLength(8),
                            ])
                            ->columns(1)
                            ->columnSpanFull(),

                        Select::make('role')
                            ->label('Role di Mitra')
                            ->options([
                                'owner'   => '👑 Owner',
                                'cashier' => '🧾 Cashier',
                                'kitchen' => '🍳 Kitchen',
                            ])
                            ->required()
                            ->default('cashier')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $organization = $livewire->getOwnerRecord();
                        $role         = $data['role'];

                        // Tentukan user — existing atau buat baru
                        if (! empty($data['user_id'])) {
                            $user = User::findOrFail($data['user_id']);
                        } elseif (! empty($data['new_user_email'])) {
                            $user = User::firstOrCreate(
                                ['email' => trim($data['new_user_email'])],
                                [
                                    'name'     => trim($data['new_user_name'] ?? $data['new_user_email']),
                                    'password' => Hash::make(
                                        filled($data['new_user_password'] ?? null)
                                            ? $data['new_user_password']
                                            : Str::random(16)
                                    ),
                                    'status' => 'active',
                                ]
                            );
                        } else {
                            Notification::make()
                                ->warning()
                                ->title('Gagal')
                                ->body('Pilih user existing atau isi email user baru.')
                                ->send();

                            return;
                        }

                        // Attach ke organization (upsert — update role jika sudah ada)
                        OrganizationMember::updateOrCreate(
                            ['organization_id' => $organization->id, 'user_id' => $user->id],
                            ['role' => $role]
                        );

                        Notification::make()
                            ->success()
                            ->title('Anggota berhasil ditambahkan')
                            ->body("{$user->name} ditambahkan sebagai {$role}.")
                            ->send();
                    }),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('edit_role')
                    ->label('Ubah Role')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->form([
                        Select::make('role')
                            ->label('Role di Mitra')
                            ->options([
                                'owner'   => '👑 Owner',
                                'cashier' => '🧾 Cashier',
                                'kitchen' => '🍳 Kitchen',
                            ])
                            ->required(),
                    ])
                    ->fillForm(fn ($record) => ['role' => $record->role])
                    ->action(function (array $data, $record): void {
                        $record->update(['role' => $data['role']]);

                        Notification::make()
                            ->success()
                            ->title('Role diperbarui')
                            ->body("Role {$record->user->name} diubah menjadi {$data['role']}.")
                            ->send();
                    }),

                \Filament\Actions\Action::make('remove_member')
                    ->label('Keluarkan')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Keluarkan Anggota?')
                    ->modalDescription(fn ($record) => "User \"{$record->user->name}\" akan dikeluarkan dari mitra ini. Data user tidak akan dihapus.")
                    ->action(function ($record): void {
                        $name = $record->user->name;
                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Anggota dikeluarkan')
                            ->body("{$name} berhasil dikeluarkan dari mitra.")
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
