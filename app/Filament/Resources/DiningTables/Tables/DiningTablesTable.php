<?php

namespace App\Filament\Resources\DiningTables\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Collection;


class DiningTablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('qr_token')
                    ->label('QR Token')
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->label('Organization')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('regenerate_qr')
                    ->label('Regenerate QR')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['qr_token' => Str::random(32)]);
                        Notification::make()
                            ->title('QR Token Regenerated')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('view_qr')
                    ->label('View QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->modalHeading(fn ($record) => 'QR Code — ' . $record->name)
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn ($record) => view(
                        'filament.dining-tables.qr-modal',
                        [
                            'record' => $record,
                            'qrUrl'  => rtrim(config('services.santap.web_url'), '/')
                                . '/o/' . $record->organization->slug
                                . '/orders?table=' . $record->qr_token,
                        ]
                    )),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('bulk_print_qr')
                        ->label('Bulk Print QR')
                        ->icon('heroicon-o-printer')
                        ->modalHeading('Print QR — Meja Terpilih')
                        ->modalWidth('4xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalContent(fn (Collection $records) => view(
                            'filament.dining-tables.bulk-qr-modal',
                            [
                                'records' => $records->load('organization'),
                                'baseUrl' => rtrim(config('services.santap.web_url'), '/'),
                            ]
                        )),

                    \Filament\Actions\BulkAction::make('bulk_copy_qr_urls')
                        ->label('Bulk Copy QR URL')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->modalHeading('QR URL — Meja Terpilih')
                        ->modalWidth('lg')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalContent(fn (Collection $records) => view(
                            'filament.dining-tables.bulk-url-modal',
                            [
                                'records' => $records->load('organization'),
                                'baseUrl' => rtrim(config('services.santap.web_url'), '/'),
                            ]
                        )),
                ]),
            ]);
    }
}
