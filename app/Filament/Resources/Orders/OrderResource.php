<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?int $navigationSort = 10;

    // admin নতুন order বানাতে পারবে না (order আসবে customer-এর কাছ থেকে)
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

    // status বদলানোর বোতাম (তালিকা ও details পেজ দুই জায়গায় ব্যবহার হয়)
    public static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Change status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->fillForm(fn (Order $record): array => ['status' => $record->status])
            ->schema([
                Select::make('status')
                    ->options(Order::statusOptions())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data, Order $record): void {
                $record->update(['status' => $data['status']]);

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