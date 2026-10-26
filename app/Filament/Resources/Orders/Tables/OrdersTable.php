<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\BlockedPhone;
use App\Models\Order;
use App\Support\ReturnGuard;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $limit = ReturnGuard::limit();
        $blocked = array_flip(BlockedPhone::pluck('phone')->all());

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('phoneReturns'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Order code')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('phone_returns_count')
                    ->label('Returns')
                    ->badge()
                    ->formatStateUsing(function ($state, Order $record) use ($blocked): string {
                        if (isset($blocked[$record->phone])) {
                            return 'Blocked';
                        }

                        return (int) $state > 0 ? (string) (int) $state : '-';
                    })
                    ->color(function ($state, Order $record) use ($blocked, $limit): string {
                        if (isset($blocked[$record->phone]) || (int) $state >= $limit) {
                            return 'danger';
                        }

                        return (int) $state > 0 ? 'warning' : 'gray';
                    })
                    ->tooltip('Returned orders from this mobile number that count (the customer\'s side). Red = at or above the limit set in Settings, or blocked.'),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::sourceOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => Order::sourceColor($state))
                    ->sortable(),
                TextColumn::make('created_by_admin')
                    ->label('Created by')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Admin' : 'Customer')
                    ->color(fn ($state): string => $state ? 'info' : 'gray'),
                TextColumn::make('total')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => Order::statusColor($state))
                    ->sortable(),
                TextColumn::make('return_reason_label')
                    ->label('Return reason')
                    ->state(fn (Order $record): ?string => $record->status === 'returned' ? Order::returnReasonLabel($record->return_reason) : null)
                    ->badge()
                    ->placeholder('-')
                    ->color(fn (Order $record): string => Order::returnReasonCounts($record->return_reason) ? 'danger' : 'success')
                    ->wrap()
                    ->tooltip(fn (Order $record): ?string => $record->return_note),
                TextColumn::make('created_at')
                    ->label('Placed at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Order::statusOptions()),
                SelectFilter::make('source')
                    ->options(Order::sourceOptions()),
                Filter::make('created_by_admin')
                    ->label('Created by admin')
                    ->query(fn ($query) => $query->where('created_by_admin', true)),
            ])
            ->recordActions([
                ViewAction::make(),
                OrderResource::changeStatusAction(),
            ]);
    }
}