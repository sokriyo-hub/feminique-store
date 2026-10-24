<?php

namespace App\Support;

use App\Models\Product;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports products in the SAME format the importer reads
 * (simple product = one row, variant product = one row per variant).
 * Always writes the BASE prices (running campaigns are never exported).
 */
class ProductCsvExporter
{
    public const HEADER = [
        'name', 'category', 'description', 'price', 'discount_price', 'stock', 'sku',
        'option1_name', 'option1_value', 'option2_name', 'option2_value', 'option3_name', 'option3_value',
    ];

    public static function download(): StreamedResponse
    {
        $paths = CategoryTree::pathOptions();

        return CsvWriter::download(
            'products-' . now()->format('Y-m-d_His') . '.csv',
            self::HEADER,
            function (callable $write) use ($paths): void {
                Product::with(['variants', 'options'])->chunkById(100, function ($products) use ($write, $paths): void {
                    foreach ($products as $product) {
                        foreach (self::rowsFor($product, $paths) as $row) {
                            $write($row);
                        }
                    }
                });
            }
        );
    }

    private static function rowsFor(Product $product, array $paths): array
    {
        $name = CsvWriter::text($product->name);
        $category = CsvWriter::text($paths[$product->category_id] ?? '');
        $description = CsvWriter::text($product->description);

        // Simple product (or a variant product that lost all its variants): one row
        if (! $product->has_variants || $product->variants->isEmpty()) {
            return [[
                $name,
                $category,
                $description,
                CsvWriter::number($product->price),
                $product->has_discount ? CsvWriter::number($product->discount_price) : '',
                $product->has_variants ? 0 : (int) $product->stock,
                CsvWriter::text($product->sku),
                '', '', '', '', '', '',
            ]];
        }

        $optionNames = $product->options->pluck('name')->take(3)->values()->all();
        $rows = [];
        $first = null;

        foreach ($product->variants->values() as $i => $variant) {
            // false = base price, without any campaign
            [$regular, $selling] = $product->variantPrices($variant, false);
            $discount = $selling < $regular ? $selling : null;

            if ($i === 0) {
                // The first row carries the product price (the importer reads it from there)
                $first = [$regular, $selling];
                $price = $regular;
            } else {
                // Same price as the first row: leave empty (the importer then uses the first row's price)
                $same = abs($regular - $first[0]) < 0.005 && abs($selling - $first[1]) < 0.005;
                $price = $same ? null : $regular;
                $discount = $same ? null : $discount;
            }

            $row = [
                $name,
                $i === 0 ? $category : '',
                $i === 0 ? $description : '',
                CsvWriter::number($price),
                CsvWriter::number($discount),
                (int) $variant->stock,
                CsvWriter::text($variant->sku),
            ];

            $combination = (array) $variant->combination;

            for ($n = 0; $n < 3; $n++) {
                $optionName = $optionNames[$n] ?? null;

                $row[] = $optionName !== null ? CsvWriter::text($optionName) : '';
                $row[] = $optionName !== null ? CsvWriter::text((string) ($combination[$optionName] ?? '')) : '';
            }

            $rows[] = $row;
        }

        return $rows;
    }
}