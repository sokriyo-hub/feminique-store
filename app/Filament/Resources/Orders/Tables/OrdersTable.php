<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
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