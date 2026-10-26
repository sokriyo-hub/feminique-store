<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Support\OrderStock;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?int $navigationSort = 10;

    // admin নতুন order বানাতে পারবে না এখানে (Order desk পেজ থেকে বানাবে)
    public static function canCreate(): bool
    {
        return false;
    }

    // বাঁ মেনুতে Pending order-এর সংখ্যা
    public static function getNavigationBadge(): ?string
    {
        $count = Order::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    // "Why was it returned?" + note. $visible = when to show them (null = always shown and required)
    public static function returnFields(?Closure $visible = null): array
    {
        $reason = Select::make('return_reason')
            ->label('Why was it returned?')
            ->options(Order::returnReasonGroups())
            ->native(false)
            ->helperText('A customer-side reason counts toward the red flag of the mobile number. A reason that is not the customer\'s fault (damaged product, wrong item ...) never counts.');

        $note = Textarea::make('return_note')
            ->label('Note (optional)')
            ->rows(2)
            ->maxLength(500);

        if ($visible) {
            $reason->visible($visible)->required($visible);
            $note->visible($visible);
        } else {
            $reason->required();
        }

        return [$reason, $note];
    }

    // status বদলানোর বোতাম (তালিকা ও details পেজ দুই জায়গায় ব্যবহার হয়)
    // Cancelled = stock ফেরে (একবারই). Returned = কারণ লাগে + admin প্রতিবার বাছে stock ফিরবে কিনা.
    // Cancelled/Returned থেকে আবার চালু = stock আবার কাটে (কম থাকলে আটকায়).
    public static function changeStatusAction(): Action
    {
        $isReturned = fn (Get $get): bool => $get('status') === 'returned';

        return Action::make('changeStatus')
            ->label('Change status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->fillForm(fn (Order $record): array => ['status' => $record->status])
            ->schema([
                Select::make('status')
                    ->options(Order::statusOptions())
                    ->required()
                    ->native(false)
                    ->live(),
                ...self::returnFields($isReturned),
                Select::make('restock')
                    ->label('What happens to the returned pieces?')
                    ->options([
                        'yes' => 'They are in good condition: add them back to stock',
                        'no' => 'Damaged or lost: do not add them back',
                    ])
                    ->native(false)
                    ->visible($isReturned)
                    ->required($isReturned)
                    ->helperText('You choose this every time. (If this order was not holding any stock, the choice changes nothing.)'),
            ])
            ->action(function (array $data, Order $record): void {
                $returned = $data['status'] === 'returned';

                $restock = ($returned && filled($data['restock'] ?? null))
                    ? $data['restock'] === 'yes'
                    : null;

                $error = OrderStock::changeStatus(
                    $record,
                    $data['status'],
                    $restock,
                    $returned ? ($data['return_reason'] ?? null) : null,
                    $returned ? ($data['return_note'] ?? null) : null,
                );

                if ($error !== null) {
                    Notification::make()
                        ->danger()
                        ->title('Status was not changed')
                        ->body($error)
                        ->persistent()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->success()
                    ->title('Status updated to ' . Order::statusOptions()[$data['status']])
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}