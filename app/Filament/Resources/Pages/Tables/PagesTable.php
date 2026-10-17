<?php

namespace App\Filament\Resources\Pages\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}