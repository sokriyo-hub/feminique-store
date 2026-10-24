<?php

namespace App\Filament\Resources\Campaigns\Tables;

use App\Models\Campaign;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['categories', 'products']))
            ->defaultSort('starts_at', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Campaign $record): string => $record->status())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'running' => 'Running',
                        'scheduled' => 'Scheduled',
                        'ended' => 'Ended',
                        default => 'Off',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'running' => 'success',
                        'scheduled' => 'info',
                        'ended' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('discount_percent')
                    ->label('Discount')
                    ->suffix('%'),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y, h:i A'),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y, h:i A'),
                TextColumn::make('applies_to')
                    ->label('Applies to')
                    ->state(function (Campaign $record): string {
                        if ($record->all_products) {
                            return 'All products';
                        }

                        $parts = [];

                        if ($record->categories_count > 0) {
                            $parts[] = $record->categories_count . ' categor' . ($record->categories_count === 1 ? 'y' : 'ies');
                        }

                        if ($record->products_count > 0) {
                            $parts[] = $record->products_count . ' product' . ($record->products_count === 1 ? '' : 's');
                        }

                        return $parts ? implode(' + ', $parts) : '-';
                    }),
                IconColumn::make('free_delivery')
                    ->label('Free delivery')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}