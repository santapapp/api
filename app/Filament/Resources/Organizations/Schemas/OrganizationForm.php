<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Section 1: Identitas Mitra ─────────────────────────────
                Section::make('Identitas Mitra')
                    ->icon('heroicon-o-building-storefront')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Mitra / Restoran')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state)))
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->readOnly()
                            ->helperText('Otomatis dibuat dari nama mitra'),

                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->helperText('Nonaktifkan untuk menangguhkan akses mitra'),

                        TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('email')
                            ->label('Email Restoran')
                            ->email()
                            ->maxLength(255),
                    ]),

                // ── Section 2: Alamat ──────────────────────────────────────
                Section::make('Alamat')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('city')
                            ->label('Kota')
                            ->maxLength(100),

                        TextInput::make('province')
                            ->label('Provinsi')
                            ->maxLength(100),

                        TextInput::make('postal_code')
                            ->label('Kode Pos')
                            ->maxLength(10),
                    ]),

                // ── Section 3: Media ───────────────────────────────────────
                // CATATAN: Section ini TIDAK boleh ->collapsed() saat awal.
                // FilePond (FileUpload) gagal menginisialisasi area klik bila
                // di-render dalam container tersembunyi → tombol upload tak bisa
                // diklik (Filament v5.6 masih terdampak utk Section).
                Section::make('Media')
                    ->icon('heroicon-o-photo')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Logo Restoran')
                            ->image()
                            ->imagePreviewHeight('100')
                            ->disk('public')
                            ->directory('organization/logos')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048),

                        FileUpload::make('banner')
                            ->label('Banner Restoran')
                            ->image()
                            ->imagePreviewHeight('100')
                            ->disk('public')
                            ->directory('organization/banners')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120),
                    ]),

                // ── Section 4: Konfigurasi Bisnis ──────────────────────────
                Section::make('Konfigurasi Bisnis')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Select::make('timezone')
                            ->label('Zona Waktu')
                            ->options([
                                'Asia/Jakarta'   => 'WIB — Asia/Jakarta',
                                'Asia/Makassar'  => 'WITA — Asia/Makassar',
                                'Asia/Jayapura'  => 'WIT — Asia/Jayapura',
                            ])
                            ->default('Asia/Jakarta')
                            ->searchable(),

                        TextInput::make('currency')
                            ->label('Kode Mata Uang')
                            ->default('IDR')
                            ->maxLength(3)
                            ->helperText('Contoh: IDR, USD'),

                        Toggle::make('tax_enabled')
                            ->label('Aktifkan Pajak (PPN)')
                            ->live()
                            ->onColor('warning'),

                        TextInput::make('tax_rate')
                            ->label('Persentase Pajak (%)')
                            ->numeric()
                            ->default(11)
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Get $get): bool => (bool) $get('tax_enabled')),

                        Toggle::make('service_charge_enabled')
                            ->label('Aktifkan Service Charge')
                            ->live()
                            ->onColor('warning'),

                        TextInput::make('service_charge_rate')
                            ->label('Persentase Service Charge (%)')
                            ->numeric()
                            ->default(5)
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Get $get): bool => (bool) $get('service_charge_enabled')),

                        Select::make('order_marker_mode')
                            ->label('Mode Nomor Penanda Pesanan')
                            ->options([
                                'disabled' => 'Nonaktif',
                                'optional' => 'Opsional',
                                'required' => 'Wajib',
                            ])
                            ->default('disabled')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state === 'disabled') {
                                    $set('order_marker_max_number', null);
                                }
                            })
                            ->helperText('Gunakan jika outlet memakai nomor akrilik/table tent/penanda fisik. Berbeda dari data meja QR Santap.')
                            ->columnSpanFull(),

                        TextInput::make('order_marker_max_number')
                            ->label('Maksimal Nomor Penanda Pesanan')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(9999)
                            ->required(fn (Get $get): bool => in_array($get('order_marker_mode'), ['optional', 'required'], true))
                            ->visible(fn (Get $get): bool => in_array($get('order_marker_mode'), ['optional', 'required'], true))
                            ->placeholder('Contoh: 30')
                            ->helperText('Nomor yang diizinkan: 1 sampai nilai ini.'),
                    ]),

                // ── Section 5: Tambah User (hanya saat Create) ────────────
                Section::make('Tambah User ke Mitra')
                    ->icon('heroicon-o-users')
                    ->description('Tambahkan user yang akan bergabung ke mitra ini. Jika email sudah terdaftar, user akan langsung di-assign.')
                    ->collapsible()
                    ->visibleOn('create')
                    ->schema([
                        Repeater::make('members_data')
                            ->label('Daftar User')
                            ->addActionLabel('+ Tambah User')
                            ->columns(2)
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Lengkap')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->helperText('Kosongkan untuk generate password otomatis')
                                    ->revealable()
                                    ->minLength(8),

                                Select::make('role')
                                    ->label('Role di Mitra')
                                    ->options([
                                        'owner'   => '👑 Owner',
                                        'cashier' => '🧾 Cashier',
                                        'kitchen' => '🍳 Kitchen',
                                    ])
                                    ->required()
                                    ->default('cashier'),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
