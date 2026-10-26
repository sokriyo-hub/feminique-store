<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\BlockedPhones\BlockedPhoneResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\ReturnGuard;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::changeStatusAction(),

            // Fix a wrong return reason later
            Action::make('editReturnReason')
                ->label('Edit return reason')
                ->icon(Heroicon::OutlinedPencil)
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === 'returned')
                ->fillForm(fn (): array => [
                    'return_reason' => $this->record->return_reason,
                    'return_note' => $this->record->return_note,
                ])
                ->schema(OrderResource::returnFields())
                ->action(function (array $data): void {
                    $this->record->update([
                        'return_reason' => $data['return_reason'],
                        'return_note' => filled($data['return_note'] ?? null) ? mb_substr(trim((string) $data['return_note']), 0, 500) : null,
                    ]);

                    Notification::make()->success()->title('Return reason saved')->send();
                }),

            Action::make('blockPhone')
                ->label('Block this number')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (): bool => ReturnGuard::blockedRecord($this->record->phone) === null)
                ->modalHeading('Block this mobile number?')
                ->modalDescription('This number will not be able to place orders on the website. You can still create orders for it in the Order desk. You can unblock it any time.')
                ->modalSubmitActionLabel('Block number')
                ->schema([
                    TextInput::make('reason')
                        ->label('Reason (optional, only you see it)')
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    ReturnGuard::block($this->record->phone, $data['reason'] ?? null);

                    Notification::make()->success()->title('Number blocked')->send();
                }),

            Action::make('numberHistory')
                ->label('Number history')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->visible(fn (): bool => ReturnGuard::blockedRecord($this->record->phone) !== null)
                ->url(fn (): string => BlockedPhoneResource::getUrl('view', ['record' => ReturnGuard::blockedRecord($this->record->phone)])),

            Action::make('unblockPhone')
                ->label('Unblock this number')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('gray')
                ->visible(fn (): bool => ReturnGuard::blockedRecord($this->record->phone) !== null)
                ->requiresConfirmation()
                ->modalHeading('Unblock this mobile number?')
                ->action(function (): void {
                    ReturnGuard::unblock($this->record->phone);

                    Notification::make()->success()->title('Number unblocked')->send();
                }),
        ];
    }
}