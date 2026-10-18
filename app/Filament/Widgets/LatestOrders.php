<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest orders')
            ->query(Order::query()->latest())
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No orders yet')
            ->columns([
                TextColumn::make('code')
                    ->label('Order code'),
                TextColumn::make('customer_name')
                    ->label('Customer'),
                TextColumn::make('total')
                    ->money('BDT'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => Order::statusColor($state)),
                TextColumn::make('created_at')
                    ->label('Placed')
                    ->since(),
            ]);
    }
}