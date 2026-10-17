<?php

namespace App\Filament\Resources\DeliveryZones\Tables;

use App\Models\DeliveryZone;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveryZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('charge')
                    ->money('BDT'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, DeliveryZone $record) {
                        $otherActiveExists = DeliveryZone::where('is_active', true)
                            ->where('id', '!=', $record->id)
                            ->exists();

                        if ($record->is_active && ! $otherActiveExists) {
                            Notification::make()
                                ->title('Cannot delete the last active zone')
                                ->body('Add or activate another zone first.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }
}