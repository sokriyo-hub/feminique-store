<?php

namespace App\Support;

use App\Models\Category;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Category list for reference (there is no category importer).
 */
class CategoryCsvExporter
{
    public const HEADER = ['path', 'name', 'slug', 'parent', 'level', 'sort_order', 'in_navbar', 'products_directly_inside'];

    public static function download(): StreamedResponse
    {
        return CsvWriter::download(
            'categories-' . now()->format('Y-m-d_His') . '.csv',
            self::HEADER,
            function (callable $write): void {
                $categories = Category::withCount('products')->get()->keyBy('id');

                foreach (CategoryTree::flat() as $row) {
                    $category = $categories->get($row['id']);

                    if (! $category) {
                        continue;
                    }

                    $isMain = $row['parent_id'] === null;

                    $write([
                        CsvWriter::text($row['path']),
                        CsvWriter::text($category->name),
                        CsvWriter::text($category->slug),
                        CsvWriter::text(implode(' > ', array_slice($row['segments'], 0, -1))),
                        $row['depth'],
                        $isMain ? (int) $category->sort_order : '',
                        $isMain ? ($category->show_in_navbar ? 'yes' : 'no') : '',
                        (int) $category->products_count,
                    ]);
                }
            }
        );
    }
}