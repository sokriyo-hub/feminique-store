<?php

namespace App\Filament\Resources\BlockedPhones;

use App\Filament\Resources\BlockedPhones\Pages\ListBlockedPhones;
use App\Filament\Resources\BlockedPhones\Pages\ViewBlockedPhone;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\BlockedPhone;
use App\Models\Order;
use App\Support\Money;
use App\Support\Phone;
use App\Support\ReturnGuard;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlockedPhoneResource extends Resource
{
    protected static ?string $model = BlockedPhone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'Blocked numbers';

    protected static ?string $modelLabel = 'blocked number';

    protected static ?string $pluralModelLabel = 'blocked numbers';

    protected static ?string $recordTitleAttribute = 'phone';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('phone')
                    ->label('Mobile number')
                    ->tel()
                    ->required()
                    ->maxLength(20)
                    ->placeholder('01XXXXXXXXX')
                    ->dehydrateStateUsing(fn ($state): ?string => Phone::normalize((string) $state))
                    ->rule(fn (?BlockedPhone $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $phone = Phone::normalize((string) $value);

                        if ($phone === null) {
                            $fail('Enter a valid mobile number, like 01712345678.');

                            return;
                        }

                        $exists = BlockedPhone::where('phone', $phone)
                            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                            ->exists();

                        if ($exists) {
                            $fail('This number is already blocked.');
                        }
                    }),
                TextInput::make('reason')
                    ->label('Reason for blocking (optional, only you see it)')
                    ->maxLength(255),
            ]);
    }

    // The history page of one blocked number
    public static function infolist(Schema $schema): Schema
    {
        $statusName = fn (string $state): string => Order::statusOptions()[$state] ?? $state;

        return $schema
            ->components([
                Section::make('Blocked number')
                    ->schema([
                        TextEntry::make('phone')
                            ->label('Mobile number')
                            ->copyable()
                            ->weight('bold'),
                        TextEntry::make('created_at')
                            ->label('Blocked on')
                            ->dateTime(),
                        TextEntry::make('reason')
                            ->label('Reason for blocking')
                            ->placeholder('-'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Summary')
                    ->schema([
                        TextEntry::make('s_orders')
                            ->label('All orders')
                            ->state(fn (BlockedPhone $record): int => ReturnGuard::history($record->phone)['orders']),
                        TextEntry::make('s_delivered')
                            ->label('Delivered')
                            ->state(fn (BlockedPhone $record): int => ReturnGuard::history($record->phone)['delivered']),
                        TextEntry::make('s_cancelled')
                            ->label('Cancelled')
                            ->state(fn (BlockedPhone $record): int => ReturnGuard::history($record->phone)['cancelled']),
                        TextEntry::make('s_counted')
                            ->label('Returned: customer side (counts)')
                            ->state(fn (BlockedPhone $record): int => ReturnGuard::history($record->phone)['counted'])
                            ->badge()
                            ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray'),
                        TextEntry::make('s_not_counted')
                            ->label('Returned: not the customer\'s fault')
                            ->state(fn (BlockedPhone $record): int => ReturnGuard::history($record->phone)['not_counted'])
                            ->badge()
                            ->color('success'),
                        TextEntry::make('s_value')
                            ->label('Value of the customer-side returns')
                            ->state(fn (BlockedPhone $record): string => Money::format(ReturnGuard::history($record->phone)['counted_value'])),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('All orders from this number')
                    ->description('Newest first. Click an order code to open the order.')
                    ->schema([
                        RepeatableEntry::make('orders')
                            ->hiddenLabel()
                            ->placeholder('No orders from this number.')
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Order')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
                                TextEntry::make('created_at')
                                    ->label('Placed')
                                    ->dateTime(),
                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing($statusName)
                                    ->color(fn (string $state): string => Order::statusColor($state)),
                                TextEntry::make('total')
                                    ->money('BDT'),
                                TextEntry::make('source')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => Order::sourceOptions()[$state] ?? $state)
                                    ->color(fn (string $state): string => Order::sourceColor($state)),
                                TextEntry::make('return_reason_label')
                                    ->label('Return reason')
                                    ->state(fn (Order $record): ?string => $record->status === 'returned' ? Order::returnReasonLabel($record->return_reason) : null)
                                    ->badge()
                                    ->placeholder('-')
                                    ->color(fn (Order $record): string => Order::returnReasonCounts($record->return_reason) ? 'danger' : 'success'),
                                TextEntry::make('returned_at')
                                    ->label('Returned on')
                                    ->dateTime()
                                    ->placeholder('-'),
                                TextEntry::make('ip_address')
                                    ->label('IP')
                                    ->placeholder('-'),
                                TextEntry::make('return_note')
                                    ->label('Note')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('orders'))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (BlockedPhone $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('phone')
                    ->label('Mobile number')
                    ->searchable(),
                TextColumn::make('reason')
                    ->label('Reason for blocking')
                    ->placeholder('-'),
                TextColumn::make('orders_count')
                    ->label('All orders'),
                TextColumn::make('counted_returns')
                    ->label('Returns (customer side)')
                    ->state(fn (BlockedPhone $record): int => ReturnGuard::returnCount($record->phone))
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Blocked on')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()->label('History'),
                EditAction::make(),
                DeleteAction::make()
                    ->label('Unblock')
                    ->modalHeading('Unblock this number?'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlockedPhones::route('/'),
            'view' => ViewBlockedPhone::route('/{record}'),
        ];
    }
}