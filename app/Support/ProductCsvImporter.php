<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductCsvImporter
{
    public const MAX_ROWS = 200;

    private const REQUIRED_COLUMNS = ['name', 'category', 'price', 'stock'];

    private const MAX_STOCK = 1000000;

    /**
     * ফেরত দেয়: ['created' => int (products), 'skipped' => int (products), 'errors' => string[], 'fatal' => ?string]
     *
     * - Same name in several rows = one product with variants (one row per variant).
     * - If any row of a product has a problem, the WHOLE product is skipped (nothing half-saved).
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

        // optionN_name and optionN_value must come as a pair
        for ($n = 1; $n <= Product::MAX_OPTIONS; $n++) {
            if (isset($index['option' . $n . '_name']) !== isset($index['option' . $n . '_value'])) {
                fclose($stream);

                return self::fatal('Columns option' . $n . '_name and option' . $n . '_value must both be in the file.');
            }
        }

        $known = array_merge(self::REQUIRED_COLUMNS, ['description', 'discount_price', 'sku']);
        for ($n = 1; $n <= Product::MAX_OPTIONS; $n++) {
            $known[] = 'option' . $n . '_name';
            $known[] = 'option' . $n . '_value';
        }

        // ---------- read all rows ----------
        $rows = [];
        $rowNumber = 1; // হেডার = row 1

        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $rowNumber++;

            if (self::isBlank($row)) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($stream);

                return self::fatal('The file has more than ' . self::MAX_ROWS . ' rows. Nothing was added. Split it into smaller files (keep all rows of one product in the same file).');
            }

            $record = ['n' => $rowNumber];

            foreach ($known as $col) {
                $record[$col] = isset($index[$col]) ? trim((string) ($row[$index[$col]] ?? '')) : '';
            }

            $rows[] = $record;
        }

        fclose($stream);

        // ---------- category paths ("Rings > Gold Rings") ----------
        $categories = [];
        foreach (CategoryTree::flat() as $category) {
            $categories[self::pathKey($category['path'])] = $category['id'];
        }

        // ---------- group rows: same name = one product ----------
        $groups = [];
        $errors = [];
        $skipped = 0;

        foreach ($rows as $r) {
            if ($r['name'] === '') {
                $errors[] = 'Row ' . $r['n'] . ': Name is missing.';
                $skipped++;

                continue;
            }

            $groups[mb_strtolower($r['name'])][] = $r;
        }

        $created = 0;

        foreach ($groups as $group) {
            $problems = self::processGroup($group, $categories);

            if (! $problems) {
                $created++;

                continue;
            }

            $skipped++;

            usort($problems, fn (array $a, array $b): int => $a[0] <=> $b[0]);

            foreach ($problems as [$n, $message]) {
                $errors[] = 'Row ' . $n . ' (' . $group[0]['name'] . '): ' . $message;
            }

            if (count($group) > 1) {
                $errors[] = 'Product "' . $group[0]['name'] . '" was NOT added (all its ' . count($group) . ' rows are skipped). Fix the rows above and upload again.';
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors, 'fatal' => null];
    }

    /**
     * Checks all rows of ONE product and saves it when everything is fine.
     * Returns the problems: [[rowNumber, message], ...]. Empty = saved.
     */
    private static function processGroup(array $rows, array $categories): array
    {
        $first = $rows[0];
        $name = $first['name'];
        $problems = [];

        $add = function (array $r, string $message) use (&$problems): void {
            $problems[] = [$r['n'], $message];
        };

        // ----- name + description (first row) -----
        if (mb_strlen($name) > 255) {
            $add($first, 'Name is too long (max 255 characters).');
        } elseif (Product::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            $add($first, 'A product with this name already exists.');
        }

        if (mb_strlen($first['description']) > 5000) {
            $add($first, 'Description is too long (max 5000 characters).');
        }

        // ----- options of every row -----
        $pairsByRow = [];

        foreach ($rows as $k => $r) {
            $pairs = [];

            for ($n = 1; $n <= Product::MAX_OPTIONS; $n++) {
                $optName = $r['option' . $n . '_name'];
                $optValue = $r['option' . $n . '_value'];

                if ($optName === '' && $optValue === '') {
                    continue;
                }

                if ($optName === '' || $optValue === '') {
                    $add($r, 'Option ' . $n . ' needs both a name and a value.');

                    continue;
                }

                if (mb_strlen($optName) > 50 || preg_match('/[=|]/', $optName)) {
                    $add($r, 'Option name "' . $optName . '" is not allowed (max 50 characters, no = or | signs).');

                    continue;
                }

                if (mb_strlen($optValue) > 100 || preg_match('/[=|]/', $optValue)) {
                    $add($r, 'Option value "' . $optValue . '" is not allowed (max 100 characters, no = or | signs).');

                    continue;
                }

                $lower = mb_strtolower($optName);

                if (isset($pairs[$lower])) {
                    $add($r, 'Option name "' . $optName . '" is used twice in this row.');

                    continue;
                }

                $pairs[$lower] = ['name' => $optName, 'value' => $optValue];
            }

            $pairsByRow[$k] = $pairs;
        }

        $isVariant = count($rows) > 1 || count($pairsByRow[0]) > 0;

        if ($isVariant) {
            $expected = array_keys($pairsByRow[0]);

            if (! $expected) {
                $add($first, 'This product has several rows, so every row needs option values (for example Size / 7).');
            } else {
                foreach ($rows as $k => $r) {
                    if ($k === 0) {
                        continue;
                    }

                    $names = array_keys($pairsByRow[$k]);

                    if (count($names) !== count($expected) || array_diff($expected, $names) || array_diff($names, $expected)) {
                        $add($r, 'Option names must be the same in every row of this product (first row: ' . implode(', ', array_column($pairsByRow[0], 'name')) . ').');
                    }
                }
            }
        }

        $structureOk = ! $problems;

        // ----- category -----
        $firstKey = self::pathKey($first['category']);
        $categoryId = null;

        if ($firstKey === '') {
            $add($first, 'Category is missing.');
        } else {
            $categoryId = $categories[$firstKey] ?? null;

            if ($categoryId === null) {
                $add($first, 'Category "' . $first['category'] . '" was not found. Use the full path (for example Rings > Gold Rings) and add it in Categories first.');
            }
        }

        foreach ($rows as $k => $r) {
            if ($k === 0) {
                continue;
            }

            $key = self::pathKey($r['category']);

            if ($key !== '' && $key !== $firstKey) {
                $add($r, 'Category must be the same as in the first row of this product.');
            }
        }

        // ----- price, stock, sku, variants (row by row) -----
        $productPrice = null;
        $productDiscount = null;
        $simpleStock = 0;
        $simpleSku = null;
        $variants = [];
        $choiceMap = [];
        $seenSkus = [];
        $seenKeys = [];

        foreach ($rows as $k => $r) {
            [$messages, $price, $discount] = self::money($r, $k === 0);

            foreach ($messages as $message) {
                $add($r, $message);
            }

            if ($k === 0 && ! $messages) {
                $productPrice = $price;
                $productDiscount = $discount;
            }

            // stock (required in every row)
            $stock = self::stock($r['stock']);

            if ($stock === null) {
                $add($r, $r['stock'] === ''
                    ? 'Stock is missing. Enter 0 if there is none.'
                    : 'Stock must be a whole number from 0 to ' . number_format(self::MAX_STOCK) . '.');
            }

            // sku (optional)
            $sku = null;

            if ($r['sku'] !== '') {
                $lowerSku = strtolower($r['sku']);

                if (! preg_match('/^[A-Za-z0-9_-]+$/', $r['sku']) || strlen($r['sku']) > 64) {
                    $add($r, 'SKU "' . $r['sku'] . '" can only have letters, numbers, dashes and underscores (max 64).');
                } elseif (isset($seenSkus[$lowerSku])) {
                    $add($r, 'SKU "' . $r['sku'] . '" is used more than once in this product.');
                } elseif (Product::where('sku', $r['sku'])->exists() || ProductVariant::where('sku', $r['sku'])->exists()) {
                    $add($r, 'SKU "' . $r['sku'] . '" is already used by another product or variant.');
                } else {
                    $sku = $r['sku'];
                }

                $seenSkus[$lowerSku] = true;
            }

            if ($k === 0) {
                $simpleStock = $stock ?? 0;
                $simpleSku = $sku;
            }

            if (! $isVariant || ! $structureOk) {
                continue;
            }

            // combination (canonical option names and choice spelling = first row)
            $combination = [];

            foreach ($pairsByRow[0] as $lower => $firstPair) {
                $pair = $pairsByRow[$k][$lower];
                $lowerValue = mb_strtolower($pair['value']);

                $choiceMap[$firstPair['name']][$lowerValue] ??= $pair['value'];
                $combination[$firstPair['name']] = $choiceMap[$firstPair['name']][$lowerValue];
            }

            $comboKey = mb_strtolower(ProductVariant::makeKey($combination));

            if (isset($seenKeys[$comboKey])) {
                $add($r, 'The combination "' . ProductVariantManager::labelOf($combination) . '" is listed twice.');
            }

            $seenKeys[$comboKey] = true;

            // the variant's own price: empty = same as the first row (the product price)
            $ownPrice = null;
            $ownDiscount = null;

            if ($productPrice !== null) {
                if ($k === 0) {
                    $effPrice = $productPrice;
                    $effDiscount = $productDiscount;
                } else {
                    $effPrice = $price ?? $productPrice;

                    if ($discount !== null) {
                        $effDiscount = $discount;
                    } elseif ($price === null || abs($price - $productPrice) < 0.005) {
                        $effDiscount = $productDiscount;
                    } else {
                        $effDiscount = null;
                    }

                    if ($effDiscount !== null && $effDiscount >= $effPrice) {
                        $add($r, 'Discounted price must be lower than the price.');
                    }
                }

                $same = abs($effPrice - $productPrice) < 0.005
                    && (($effDiscount === null && $productDiscount === null)
                        || ($effDiscount !== null && $productDiscount !== null && abs($effDiscount - $productDiscount) < 0.005));

                if (! $same) {
                    $ownPrice = $effPrice;
                    $ownDiscount = $effDiscount;
                }
            }

            $variants[] = [
                'combination' => $combination,
                'sku' => $sku,
                'stock' => $stock ?? 0,
                'price' => $ownPrice,
                'discount' => $ownDiscount,
            ];
        }

        if ($isVariant && count($rows) > ProductVariant::MAX_PER_PRODUCT) {
            $add($first, 'A product can have at most ' . ProductVariant::MAX_PER_PRODUCT . ' variants.');
        }

        if ($problems) {
            return $problems;
        }

        // ---------- everything is fine: save the product (all or nothing) ----------
        try {
            DB::transaction(function () use ($first, $name, $categoryId, $productPrice, $productDiscount, $isVariant, $simpleStock, $simpleSku, $choiceMap, $variants): void {
                $product = Product::create([
                    'category_id' => $categoryId,
                    'name' => $name,
                    'slug' => self::uniqueSlug($name),
                    'sku' => $isVariant ? null : $simpleSku,
                    'description' => $first['description'] !== '' ? $first['description'] : null,
                    'price' => $productPrice,
                    'discount_price' => $productDiscount,
                    'has_variants' => $isVariant,
                    'stock' => $isVariant ? 0 : $simpleStock,
                ]);

                if (! $isVariant) {
                    return;
                }

                $order = 0;

                foreach ($choiceMap as $optionName => $choices) {
                    ProductOption::create([
                        'product_id' => $product->id,
                        'name' => (string) $optionName,
                        'choices' => array_values($choices),
                        'sort_order' => ++$order,
                    ]);
                }

                foreach ($variants as $i => $variant) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $variant['sku'],
                        'combination' => $variant['combination'],
                        'combination_key' => ProductVariant::makeKey($variant['combination']),
                        'price' => $variant['price'],
                        'discount_price' => $variant['discount'],
                        'stock' => $variant['stock'],
                        'sort_order' => $i + 1,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return [[$first['n'], 'could not be saved.']];
        }

        return [];
    }

    // নমুনা CSV-এর লেখা (Excel-এ বাংলা ঠিক রাখতে BOM সহ)
    public static function sampleCsv(): string
    {
        $lines = [
            'name,category,description,price,discount_price,stock,sku,option1_name,option1_value,option2_name,option2_value,option3_name,option3_value',
            // 1) simple product: no option columns, one row
            'Sample Pearl Ring,Rings,A simple product: no option columns are filled,1200,950,10,SMP-001,,,,,,',
            // 2) one option (Length), same price for every row. Later rows may leave category, description and price empty
            'Sample Charm Bracelet,Bracelets,One option (Length). Description is only in the first row,1500,1200,5,SMP-002-S,Length,Short,,,,',
            'Sample Charm Bracelet,,,,,8,SMP-002-L,Length,Long,,,,',
            // 3) two options (Size and Color)
            'Sample Stone Ring,Rings,Two options (Size and Color),1800,,3,SMP-003-6G,Size,6,Color,Gold,,',
            'Sample Stone Ring,,,,,0,SMP-003-6S,Size,6,Color,Silver,,',
            'Sample Stone Ring,,,,,4,SMP-003-7G,Size,7,Color,Gold,,',
            'Sample Stone Ring,,,,,2,SMP-003-7S,Size,7,Color,Silver,,',
            // 4) variants with their own prices (last one has no discount)
            'Sample Layered Necklace,Necklaces,Each length has its own price,2000,1700,6,SMP-004-16,Length,16 inch,,,,',
            'Sample Layered Necklace,,,2300,1950,4,SMP-004-18,Length,18 inch,,,,',
            'Sample Layered Necklace,,,2600,,2,SMP-004-20,Length,20 inch,,,,',
        ];

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    private static function fatal(string $message): array
    {
        return ['created' => 0, 'skipped' => 0, 'errors' => [], 'fatal' => $message];
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

    // "Rings > Gold Rings" -> "rings > gold rings" (extra spaces removed). Empty = no category.
    private static function pathKey(string $path): string
    {
        $parts = array_map(fn (string $part): string => (string) preg_replace('/\s+/u', ' ', trim($part)), explode('>', $path));
        $parts = array_filter($parts, fn (string $part): bool => $part !== '');

        return mb_strtolower(implode(' > ', $parts));
    }

    // Price + discount of one row. Returns [messages, price|null, discount|null]
    private static function money(array $r, bool $priceRequired): array
    {
        $price = self::number($r['price']);
        $discount = self::number($r['discount_price']);

        $validator = Validator::make([
            'price' => $price === '' ? null : $price,
            'discount_price' => $discount === '' ? null : $discount,
        ], [
            'price' => ['bail', $priceRequired ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:99999999'],
            'discount_price' => ['bail', 'nullable', 'numeric', 'gt:0', 'max:99999999'],
        ]);

        if ($validator->fails()) {
            return [$validator->errors()->all(), null, null];
        }

        $p = $price === '' ? null : (float) $price;
        $d = $discount === '' ? null : (float) $discount;

        if ($p !== null && $d !== null && $d >= $p) {
            return [['Discounted price must be lower than the price.'], null, null];
        }

        return [[], $p, $d];
    }

    // Whole number 0 ... 1,000,000 (or null when empty / wrong)
    private static function stock(string $raw): ?int
    {
        $value = str_replace([',', ' '], '', self::normalizeDigits($raw));

        if ($value === '' || ! preg_match('/^\d{1,7}$/', $value)) {
            return null;
        }

        $int = (int) $value;

        return $int <= self::MAX_STOCK ? $int : null;
    }

    private static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);
    }

    // "1,200", "Tk 750", "৳500", "১২০০" সবই সংখ্যা হিসেবে পড়া
    private static function number(string $value): string
    {
        $value = self::normalizeDigits($value);
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