<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductCsvImporter
{
    public const MAX_ROWS = 200;

    private const REQUIRED_COLUMNS = ['name', 'category', 'price'];

    /**
     * ফেরত দেয়: ['created' => int, 'errors' => string[], 'fatal' => ?string]
     */
    public static function import(string $filePath): array
    {
        $content = @file_get_contents($filePath);

        if ($content === false || trim($content) === '') {
            return self::fatal('The file is empty or could not be read.');
        }

        // Excel-এর BOM চিহ্ন সরানো
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            return self::fatal('The file is not UTF-8. In Excel choose Save As > "CSV UTF-8 (Comma delimited)" and upload again.');
        }

        $firstLine = (string) strtok($content, "\r\n");
        $delimiter = self::detectDelimiter($firstLine);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $headerRow = fgetcsv($stream, null, $delimiter, '"', '');

        if ($headerRow === false || $headerRow === [null]) {
            fclose($stream);

            return self::fatal('The first row must contain the column names.');
        }

        $index = [];
        foreach ($headerRow as $i => $title) {
            $key = str_replace(' ', '_', Str::lower(trim((string) $title)));
            if ($key !== '' && ! isset($index[$key])) {
                $index[$key] = $i;
            }
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($index)));

        if ($missing) {
            fclose($stream);

            return self::fatal('Missing column(s): ' . implode(', ', $missing) . '. Download the sample CSV to see the correct columns.');
        }

        $categories = Category::all()->mapWithKeys(
            fn (Category $c) => [mb_strtolower(trim($c->name)) => $c->id]
        );

        $seenNames = [];
        $created = 0;
        $errors = [];
        $rowNumber = 1; // হেডার = row 1
        $dataRows = 0;

        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $rowNumber++;

            if (self::isBlank($row)) {
                continue;
            }

            $dataRows++;

            if ($dataRows > self::MAX_ROWS) {
                $errors[] = 'Stopped at row ' . $rowNumber . ': a file can have at most ' . self::MAX_ROWS . ' rows. Split the rest into another file.';
                break;
            }

            $get = fn (string $col): string => isset($index[$col]) ? trim((string) ($row[$index[$col]] ?? '')) : '';

            $name = $get('name');
            $categoryName = $get('category');
            $description = $get('description');
            $price = self::number($get('price'));
            $discount = self::number($get('discount_price'));

            $problems = [];

            $validator = Validator::make([
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'discount_price' => $discount === '' ? null : $discount,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'price' => ['bail', 'required', 'numeric', 'gt:0', 'max:99999999'],
                'discount_price' => ['bail', 'nullable', 'numeric', 'gt:0', 'lt:price'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $problems[] = $message;
                }
            }

            $categoryId = null;
            if ($categoryName === '') {
                $problems[] = 'Category is missing.';
            } else {
                $categoryId = $categories[mb_strtolower($categoryName)] ?? null;
                if ($categoryId === null) {
                    $problems[] = 'Category "' . $categoryName . '" was not found. Add it in Categories first.';
                }
            }

            if ($name !== '') {
                $nameKey = mb_strtolower($name);

                if (isset($seenNames[$nameKey])) {
                    $problems[] = 'Same name already used in row ' . $seenNames[$nameKey] . ' of this file.';
                } elseif (Product::where('name', $name)->exists()) {
                    $problems[] = 'A product with this name already exists.';
                }
            }

            $label = 'Row ' . $rowNumber . ($name !== '' ? ' (' . $name . ')' : '');

            if ($problems) {
                $errors[] = $label . ': ' . implode(' ', $problems);

                continue;
            }

            try {
                Product::create([
                    'category_id' => $categoryId,
                    'name' => $name,
                    'slug' => self::uniqueSlug($name),
                    'description' => $description !== '' ? $description : null,
                    'price' => $price,
                    'discount_price' => $discount !== '' ? $discount : null,
                ]);

                $seenNames[mb_strtolower($name)] = $rowNumber;
                $created++;
            } catch (\Throwable $e) {
                report($e);
                $errors[] = $label . ': could not be saved.';
            }
        }

        fclose($stream);

        return ['created' => $created, 'errors' => $errors, 'fatal' => null];
    }

    // নমুনা CSV-এর লেখা (Excel-এ বাংলা ঠিক রাখতে BOM সহ)
    public static function sampleCsv(): string
    {
        $lines = [
            'name,category,description,price,discount_price',
            'Sample Pearl Ring,Rings,A short description of the product,1200,950',
            'Sample Charm Bracelet,Bracelets,Leave discount_price empty if there is no discount,1500,',
        ];

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    private static function fatal(string $message): array
    {
        return ['created' => 0, 'errors' => [], 'fatal' => $message];
    }

    private static function isBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function detectDelimiter(string $line): string
    {
        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    // "1,200", "Tk 750", "৳500", "১২০০" সবই সংখ্যা হিসেবে পড়া
    private static function number(string $value): string
    {
        $value = strtr($value, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);

        $value = str_ireplace(['tk', 'bdt', '৳', '/-', ',', ' '], '', $value);

        return trim($value);
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product-' . Str::lower(Str::random(6));
        $slug = $base;
        $n = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }
}