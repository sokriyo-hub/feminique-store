<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\CategoryCsvExporter;
use App\Support\OrderCsvExporter;
use App\Support\ProductCsvExporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class DataTools extends Page
{
    // Only orders older than this many days can be deleted. (Customer Favorites and the
    // dashboard charts use the last 90 days, so younger orders must stay.)
    public const MIN_ORDER_AGE_DAYS = 90;

    // Orders that are finished. Pending / Confirmed / Shipped can never be deleted here.
    public const DELETABLE_STATUSES = ['delivered', 'cancelled'];

    protected string $view = 'filament.pages.data-tools';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Export & maintenance';

    protected static ?string $title = 'Export & maintenance';

    protected static ?string $slug = 'data-tools';

    protected static ?int $navigationSort = 40;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportProducts')
                ->label('Export products')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => ProductCsvExporter::download()),

            Action::make('exportCategories')
                ->label('Export categories')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => CategoryCsvExporter::download()),

            Action::make('exportOrders')
                ->label('Export orders')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->modalHeading('Export orders')
                ->modalDescription('Leave everything empty to export all orders.')
                ->modalSubmitActionLabel('Download CSV')
                ->schema([
                    DatePicker::make('from')
                        ->label('Placed from (optional)')
                        ->native(false),
                    DatePicker::make('to')
                        ->label('Placed until (optional)')
                        ->native(false),
                    Select::make('statuses')
                        ->label('Only these statuses (optional)')
                        ->multiple()
                        ->options(Order::statusOptions()),
                ])
                ->action(fn (array $data) => OrderCsvExporter::download(
                    $data['from'] ?? null,
                    $data['to'] ?? null,
                    (array) ($data['statuses'] ?? []),
                )),

            Action::make('deleteOldOrders')
                ->label('Delete old orders')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->modalHeading('Delete old orders')
                ->modalDescription('Only Delivered and Cancelled orders older than ' . self::MIN_ORDER_AGE_DAYS . ' days are deleted. This cannot be undone. Download the orders CSV first.')
                ->modalSubmitActionLabel('Delete forever')
                ->schema([
                    DatePicker::make('before')
                        ->label('Delete orders placed before')
                        ->required()
                        ->native(false)
                        ->maxDate(fn () => now()->subDays(self::MIN_ORDER_AGE_DAYS)->startOfDay())
                        ->helperText('Orders placed before this date. The newest date you can pick is ' . self::MIN_ORDER_AGE_DAYS . ' days ago.'),
                    Toggle::make('exported')
                        ->label('I downloaded the orders CSV for this period and checked that it opens correctly')
                        ->accepted(),
                    TextInput::make('confirm')
                        ->label('Type DELETE to confirm')
                        ->required()
                        ->rules(['in:DELETE']),
                ])
                ->action(function (array $data): void {
                    $cutoff = Carbon::parse($data['before'])->startOfDay();

                    // Checked again on the server (never trust the form alone)
                    if ($cutoff->greaterThan(now()->subDays(self::MIN_ORDER_AGE_DAYS)->startOfDay())) {
                        Notification::make()
                            ->danger()
                            ->title('That date is too recent')
                            ->body('Only orders older than ' . self::MIN_ORDER_AGE_DAYS . ' days can be deleted.')
                            ->send();

                        return;
                    }

                    $deleted = 0;

                    Order::whereIn('status', self::DELETABLE_STATUSES)
                        ->where('created_at', '<', $cutoff)
                        ->chunkById(200, function ($orders) use (&$deleted): void {
                            DB::transaction(function () use ($orders, &$deleted): void {
                                $ids = $orders->pluck('id');

                                OrderItem::whereIn('order_id', $ids)->delete();
                                Order::whereIn('id', $ids)->delete();

                                $deleted += $ids->count();
                            });
                        });

                    Notification::make()
                        ->success()
                        ->title($deleted . ' order(s) deleted')
                        ->send();
                }),

            Action::make('clearCache')
                ->label('Clear cache')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Clear cache')
                ->modalDescription('Clears the site\'s temporary data and prepares the pages again. You stay logged in. Products and orders are not touched.')
                ->modalSubmitActionLabel('Clear cache')
                ->action(function (): void {
                    $problems = [];

                    try {
                        Artisan::call('cache:clear');
                    } catch (Throwable $e) {
                        report($e);
                        $problems[] = 'cache';
                    }

                    try {
                        Artisan::call('view:clear');
                        Artisan::call('view:cache');
                    } catch (Throwable $e) {
                        report($e);
                        $problems[] = 'pages';
                    }

                    if ($problems) {
                        Notification::make()
                            ->warning()
                            ->title('Done, but part of it failed: ' . implode(', ', $problems))
                            ->body('The site still works. Please try once more.')
                            ->send();

                        return;
                    }

                    Notification::make()->success()->title('Cache cleared')->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        $cutoff = now()->subDays(self::MIN_ORDER_AGE_DAYS)->startOfDay();

        return [
            'minDays' => self::MIN_ORDER_AGE_DAYS,
            'ordersTotal' => Order::count(),
            'deletable' => Order::whereIn('status', self::DELETABLE_STATUSES)->where('created_at', '<', $cutoff)->count(),
            'dbSize' => self::databaseSize(),
        ];
    }

    // Size of the database in MB (MySQL / MariaDB / SQLite). null = unknown.
    private static function databaseSize(): ?string
    {
        try {
            $driver = DB::getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $bytes = (int) DB::scalar('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()');
            } elseif ($driver === 'sqlite') {
                $bytes = (int) @filesize((string) DB::connection()->getDatabaseName());
            } else {
                return null;
            }

            return number_format($bytes / 1048576, 1) . ' MB';
        } catch (Throwable) {
            return null;
        }
    }
}