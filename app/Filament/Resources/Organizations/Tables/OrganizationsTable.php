<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // ── Logo ──────────────────────────────────────────────────
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(asset('images/logo-placeholder.svg')),

                // ── Nama & Slug ───────────────────────────────────────────
                TextColumn::make('name')
                    ->label('Nama Mitra')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->city ? "📍 {$record->city}" : null),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Owner ─────────────────────────────────────────────────
                TextColumn::make('owners.name')
                    ->label('Owner')
                    ->badge()
                    ->color('danger')
                    ->placeholder('—')
                    ->searchable(),

                // ── Ringkasan Members ──────────────────────────────────────
                TextColumn::make('members_summary')
                    ->label('Komposisi Tim')
                    ->state(function ($record): string {
                        $counts = $record->memberRecords()
                            ->select('role', \DB::raw('count(*) as total'))
                            ->groupBy('role')
                            ->pluck('total', 'role');

                        $parts = [];
                        if ($counts->has('owner'))   $parts[] = "👑 {$counts['owner']} Owner";
                        if ($counts->has('cashier'))  $parts[] = "🧾 {$counts['cashier']} Cashier";
                        if ($counts->has('kitchen'))  $parts[] = "🍳 {$counts['kitchen']} Kitchen";

                        return empty($parts) ? '—' : implode(' | ', $parts);
                    })
                    ->placeholder('—')
                    ->wrap(),

                // ── Total Members ──────────────────────────────────────────
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Total User')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->sortable(),

                // ── Status ────────────────────────────────────────────────
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->alignCenter(),

                // ── Tanggal ───────────────────────────────────────────────
                TextColumn::make('created_at')
                    ->label('Bergabung')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Update Terakhir')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->placeholder('Semua'),

                SelectFilter::make('city')
                    ->label('Kota')
                    ->options(fn () => \App\Models\Organization::query()
                        ->whereNotNull('city')
                        ->distinct()
                        ->pluck('city', 'city')
                        ->toArray()
                    )
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detail'),
                EditAction::make()
                    ->label('Edit'),
                Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->is_active ? 'Nonaktifkan Mitra?' : 'Aktifkan Mitra?')
                    ->modalDescription(fn ($record) => $record->is_active
                        ? "Mitra \"{$record->name}\" akan dinonaktifkan dan tidak dapat menerima order baru."
                        : "Mitra \"{$record->name}\" akan diaktifkan kembali."
                    )
                    ->action(fn ($record) => $record->update(['is_active' => ! $record->is_active]))
                    ->successNotificationTitle(fn ($record) => $record->is_active ? 'Mitra dinonaktifkan' : 'Mitra diaktifkan'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Aktifkan Terpilih')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true])),

                    BulkAction::make('deactivate')
                        ->label('Nonaktifkan Terpilih')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),

                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih'),
                ]),
            ]);
    }
}
