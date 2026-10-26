<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Support\ReturnGuard;
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
                        TextEntry::make('source')
                            ->label('Source')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Order::sourceOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => Order::sourceColor($state)),
                        TextEntry::make('created_by')
                            ->label('Created by')
                            ->state(fn (Order $record): string => $record->created_by_admin ? 'Admin (Order desk)' : 'Customer (website)'),
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
                Section::make('Return details')
                    ->visible(fn (Order $record): bool => $record->status === 'returned')
                    ->schema([
                        TextEntry::make('return_reason_label')
                            ->label('Reason')
                            ->state(fn (Order $record): string => Order::returnReasonLabel($record->return_reason))
                            ->badge()
                            ->color(fn (Order $record): string => Order::returnReasonCounts($record->return_reason) ? 'danger' : 'success'),
                        TextEntry::make('return_counts')
                            ->label('Counts toward the red flag?')
                            ->state(fn (Order $record): string => Order::returnReasonCounts($record->return_reason) ? 'Yes' : "No (not the customer's fault)"),
                        TextEntry::make('returned_at')
                            ->label('Returned on')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('return_note')
                            ->label('Note')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                                Section::make('Risk check')
                    ->description('Only you see this. A number with many returned orders is a risk for Cash on Delivery.')
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('IP address (website orders)')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('returns')
                            ->label('Returned orders from this number')
                            ->state(fn (Order $record): string => ReturnGuard::returnCount($record->phone) . ' (red flag from ' . ReturnGuard::limit() . ')')
                            ->badge()
                            ->color(fn (Order $record): string => ReturnGuard::isFlagged($record->phone)
                                ? 'danger'
                                : (ReturnGuard::returnCount($record->phone) > 0 ? 'warning' : 'gray')),
                        TextEntry::make('website_ordering')
                            ->label('Website ordering')
                            ->state(fn (Order $record): string => ReturnGuard::blockedRecord($record->phone) ? 'Blocked' : 'Allowed')
                            ->badge()
                            ->color(fn (Order $record): string => ReturnGuard::blockedRecord($record->phone) ? 'danger' : 'success'),
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
                        TextEntry::make('discount')
                            ->label('Discount')
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
                    ->columns(5)
                    ->columnSpanFull(),
            ]);
    }
}