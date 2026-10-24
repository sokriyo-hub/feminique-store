<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\AdSlots;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class ManageAdSlots extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Ad places';

    protected static ?string $title = 'Ad places';

    protected static ?string $slug = 'ad-places';

    protected static ?int $navigationSort = 22;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $values = [];

        foreach (array_keys(AdSlots::SLOTS) as $key) {
            $values['slot_' . $key] = AdSlots::isOn($key);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (AdSlots::grouped() as $page => $slots) {
            $fields = [];

            foreach ($slots as $key => $place) {
                $fields[] = Toggle::make('slot_' . $key)->label($place);
            }

            $section = Section::make($page)->schema($fields);

            if (! $sections) {
                $section->description('Switch a place off to hide every ad and campaign banner there. Nothing is deleted. Switch it on again and they come back.');
            }

            $sections[] = $section;
        }

        return $schema
            ->components([
                Form::make($sections)
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $off = [];

        foreach (array_keys(AdSlots::SLOTS) as $key) {
            if (empty($data['slot_' . $key])) {
                $off[] = $key;
            }
        }

        Setting::put('ad_slots_off', json_encode($off));
        AdSlots::flush();

        Notification::make()
            ->success()
            ->title('Ad places saved')
            ->send();
    }
}