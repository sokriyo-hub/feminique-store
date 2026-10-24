<?php

namespace App\Filament\Resources\Ads\Tables;

use App\Models\Ad;
use App\Support\AdSlots;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->columns([
                ImageColumn::make('preview')
                    ->label('Preview')
                    ->disk('public')
                    ->state(fn (Ad $record): ?string => $record->thumb()),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->state(fn (Ad $record): string => match ($record->kind()) {
                        'video' => 'Video',
                        'gif' => 'GIF',
                        default => 'Picture',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Ad $record): string => $record->status())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'live' => 'Live',
                        'scheduled' => 'Scheduled',
                        'ended' => 'Ended',
                        default => 'Off',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'success',
                        'scheduled' => 'info',
                        'ended' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('places')
                    ->label('Places')
                    ->state(fn (Ad $record): string => count((array) $record->placements) . ' place(s)')
                    ->tooltip(fn (Ad $record): string => collect((array) $record->placements)
                        ->map(fn (string $slot): string => AdSlots::label($slot))
                        ->implode("\n")),
                TextColumn::make('warning')
                    ->label('Warning')
                    ->badge()
                    ->color('danger')
                    ->state(fn (Ad $record): ?string => $record->conflicts() ? 'Shares a place' : null)
                    ->tooltip(fn (Ad $record): ?string => $record->conflicts()
                        ? 'Another ad or banner uses the same place: '
                            . implode(', ', array_map(fn (string $slot): string => AdSlots::label($slot), $record->conflicts()))
                            . '. Only the oldest one is shown. Edit or turn off one of them.'
                        : null),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('-'),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('-'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}