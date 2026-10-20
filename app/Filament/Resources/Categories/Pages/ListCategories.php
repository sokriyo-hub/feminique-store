<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Support\CategoryTree;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    // Custom tree view instead of the normal table
    protected string $view = 'filament.resources.categories.pages.list-categories';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getViewData(): array
    {
        return ['tree' => CategoryTree::tree()];
    }

    // Drag and drop: new order of the MAIN categories (sub-categories are always A-Z)
    public function reorderTop(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $topIds = Category::whereNull('parent_id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $ordered = array_values(array_intersect($ids, $topIds));
        $ordered = array_merge($ordered, array_values(array_diff($topIds, $ordered)));

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $i => $id) {
                Category::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        Notification::make()->success()->title('Order saved')->send();
    }

    public function deleteCategory(int $id): void
    {
        $category = Category::find($id);

        if (! $category) {
            return;
        }

        if ($category->children()->exists()) {
            Notification::make()
                ->title('Cannot delete this category')
                ->body('It still has sub-categories. Move or delete those first.')
                ->danger()
                ->send();

            return;
        }

        if ($category->products()->exists()) {
            Notification::make()
                ->title('Cannot delete this category')
                ->body('It still has products. Move or delete those products first.')
                ->danger()
                ->send();

            return;
        }

        $category->delete();

        Notification::make()->success()->title('Category deleted')->send();
    }
}