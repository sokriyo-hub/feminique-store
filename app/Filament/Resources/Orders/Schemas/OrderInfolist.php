<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Order code')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Order::statusOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => Order::statusColor($state)),
                        TextEntry::make('created_at')
                            ->label('Placed at')
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Customer')
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('Name'),
                        TextEntry::make('phone')
                            ->copyable(),
                        TextEntry::make('email')
                            ->placeholder('-'),
                        TextEntry::make('address')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label('Product'),
                                TextEntry::make('variant_label')
                                    ->label('Variant')
                                    ->placeholder('-'),
                                TextEntry::make('sku')
                                    ->label('SKU')
                                    ->placeholder('-'),
                                TextEntry::make('quantity')
                                    ->label('Qty'),
                                TextEntry::make('unit_price')
                                    ->label('Unit price')
                                    ->money('BDT'),
                                TextEntry::make('line_total')
                                    ->label('Line total')
                                    ->money('BDT'),
                            ])
                            ->columns(6),
                    ])
                    ->columnSpanFull(),
                Section::make('Totals')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->money('BDT'),
                        TextEntry::make('delivery_zone_name')
                            ->label('Delivery zone')
                            ->placeholder('-'),
                        TextEntry::make('delivery_charge')
                            ->label('Delivery charge')
                            ->money('BDT'),
                        TextEntry::make('total')
                            ->money('BDT')
                            ->weight('bold'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }
}