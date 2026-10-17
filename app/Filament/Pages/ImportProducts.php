<?php

namespace App\Filament\Pages;

use App\Support\ProductCsvImporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read Schema $form
 */
class ImportProducts extends Page
{
    protected string $view = 'filament.pages.import-products';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import products';

    protected static ?string $title = 'Import products from CSV';

    protected static ?string $slug = 'import-products';

    protected static ?int $navigationSort = 30;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    FileUpload::make('csv')
                        ->label('CSV file')
                        ->disk('local')
                        ->directory('csv-imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->maxSize(1024)
                        ->required()
                        ->helperText('Max 1 MB and 200 rows. Columns: name, category, description, price, discount_price. In Excel save as "CSV UTF-8". Categories must already exist.'),
                ])
                    ->livewireSubmitHandler('import')
                    ->footer([
                        Actions::make([
                            Action::make('import')
                                ->label('Import products')
                                ->submit('import'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sample')
                ->label('Download sample CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    function () {
                        echo ProductCsvImporter::sampleCsv();
                    },
                    'sample-products.csv',
                    ['Content-Type' => 'text/csv; charset=UTF-8']
                )),
        ];
    }

    public function import(): void
    {
        $data = $this->form->getState();

        $path = $data['csv'] ?? null;
        if (is_array($path)) {
            $path = array_values($path)[0] ?? null;
        }

        if (! $path) {
            return;
        }

        try {
            $this->result = ProductCsvImporter::import(Storage::disk('local')->path($path));
        } finally {
            Storage::disk('local')->delete($path);
        }

        $this->form->fill();

        if ($this->result['fatal']) {
            Notification::make()->danger()->title('Import failed')->body($this->result['fatal'])->send();

            return;
        }

        $failed = count($this->result['errors']);

        Notification::make()
            ->title($this->result['created'] . ' product(s) added' . ($failed ? ', ' . $failed . ' row(s) skipped' : ''))
            ->color($failed ? 'warning' : 'success')
            ->send();
    }
}