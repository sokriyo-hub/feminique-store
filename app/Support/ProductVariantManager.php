<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Everything about variants in the admin product form:
 * fill the form, validate before saving, generate combinations, save to the database.
 */
class ProductVariantManager
{
    // Image 1 ... Image 8 can be tied to an option choice (same as the max number of images)
    public const MAX_IMAGE_SLOTS = 8;

    // ---------- small helpers ----------

    // ['Size' => '7', 'Color' => 'Gold'] -> "Size: 7, Color: Gold"
    public static function labelOf(array $combination): string
    {
        $parts = [];

        foreach ($combination as $name => $value) {
            $parts[] = $name . ': ' . $value;
        }

        return implode(', ', $parts);
    }

    // Key of a combination in the order of the current option names (null = does not match the options)
    public static function orderedKey(array $combination, array $names): ?string
    {
        if (count($combination) !== count($names)) {
            return null;
        }

        $ordered = [];

        foreach ($names as $name) {
            if (! array_key_exists($name, $combination)) {
                return null;
            }

            $ordered[$name] = (string) $combination[$name];
        }

        return ProductVariant::makeKey($ordered);
    }

    // Cleans the options typed in the form. Returns [options, errorMessage|null]
    public static function inspectOptions(array $raw): array
    {
        $options = [];
        $seenNames = [];

        foreach (array_values($raw) as $item) {
            $item = (array) $item;
            $name = trim((string) ($item['name'] ?? ''));
            $choices = [];

            foreach ((array) ($item['choices'] ?? []) as $choice) {
                $choice = trim((string) $choice);

                if ($choice !== '') {
                    $choices[] = $choice;
                }
            }

            if ($name === '' && ! $choices) {
                continue; // empty row, ignore
            }

            if ($name === '') {
                return [[], 'Every option needs a name (for example Size or Color).'];
            }

            if (mb_strlen($name) > 50 || preg_match('/[=|]/', $name)) {
                return [[], 'Option name "' . $name . '" is not allowed. Use up to 50 characters and do not use the signs = or |.'];
            }

            if (isset($seenNames[mb_strtolower($name)])) {
                return [[], 'Option name "' . $name . '" is used twice.'];
            }

            $seenNames[mb_strtolower($name)] = true;

            if (! $choices) {
                return [[], 'Option "' . $name . '" needs at least one choice.'];
            }

            if (count($choices) > ProductVariant::MAX_PER_PRODUCT) {
                return [[], 'Option "' . $name . '" has too many choices.'];
            }

            $seenChoices = [];

            foreach ($choices as $choice) {
                if (mb_strlen($choice) > 100 || preg_match('/[=|]/', $choice)) {
                    return [[], 'Choice "' . $choice . '" in "' . $name . '" is not allowed. Use up to 100 characters and do not use the signs = or |.'];
                }

                if (isset($seenChoices[mb_strtolower($choice)])) {
                    return [[], 'Choice "' . $choice . '" is used twice in "' . $name . '".'];
                }

                $seenChoices[mb_strtolower($choice)] = true;
            }

            $options[] = ['name' => $name, 'choices' => $choices];
        }

        if (! $options) {
            return [[], 'Add at least one option (for example Size) with its choices.'];
        }

        if (count($options) > Product::MAX_OPTIONS) {
            return [[], 'Only ' . Product::MAX_OPTIONS . ' options are allowed per product.'];
        }

        return [$options, null];
    }

    // ["Color=Gold" => "Color: Gold", ...] for the "Photos for a choice" dropdowns (lenient, used while typing)
    public static function bindingOptions(array $raw): array
    {
        $out = [];

        foreach (array_values($raw) as $item) {
            $item = (array) $item;
            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            foreach ((array) ($item['choices'] ?? []) as $choice) {
                $choice = trim((string) $choice);

                if ($choice !== '') {
                    $out[$name . '=' . $choice] = $name . ': ' . $choice;
                }
            }
        }

        return $out;
    }

    // ---------- "Generate combinations" button ----------

    /**
     * Builds the variant rows from the options. Rows that already exist keep their sku/price/stock.
     * Returns [rows, errorMessage|null]
     */
    public static function generateRows(array $rawOptions, array $existingRows): array
    {
        [$options, $error] = self::inspectOptions($rawOptions);

        if ($error) {
            return [[], $error];
        }

        $names = array_column($options, 'name');

        $total = 1;
        foreach ($options as $option) {
            $total *= count($option['choices']);
        }

        if ($total > ProductVariant::MAX_PER_PRODUCT) {
            return [[], 'These options make ' . $total . ' combinations, but the limit is ' . ProductVariant::MAX_PER_PRODUCT . '. Remove some choices.'];
        }

        $existing = [];

        foreach ($existingRows as $row) {
            $row = (array) $row;
            $key = self::orderedKey((array) ($row['combination'] ?? []), $names);

            if ($key !== null) {
                $existing[$key] = $row;
            }
        }

        $combinations = [[]];

        foreach ($options as $option) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($option['choices'] as $choice) {
                    $next[] = $combination + [$option['name'] => $choice];
                }
            }

            $combinations = $next;
        }

        $rows = [];

        foreach ($combinations as $combination) {
            $old = $existing[ProductVariant::makeKey($combination)] ?? [];

            $rows[(string) Str::uuid()] = [
                'combination' => $combination,
                'label' => self::labelOf($combination),
                'sku' => $old['sku'] ?? null,
                'price' => $old['price'] ?? null,
                'discount_price' => $old['discount_price'] ?? null,
                'stock' => $old['stock'] ?? 0,
            ];
        }

        return [$rows, null];
    }

    // ---------- Edit page: fill the form ----------

    public static function fill(array $data, Product $record): array
    {
        $images = $record->images()->get();

        $data['image_paths'] = $images->pluck('path')->all();

        $data['options'] = $record->options()->get()
            ->map(fn (ProductOption $option) => [
                'name' => $option->name,
                'choices' => array_values((array) $option->choices),
            ])
            ->all();

        $data['variants'] = $record->variants()->get()
            ->map(fn (ProductVariant $variant) => [
                'combination' => (array) $variant->combination,
                'label' => $variant->label(),
                'sku' => $variant->sku,
                'price' => $variant->price !== null ? (float) $variant->price : null,
                'discount_price' => $variant->discount_price !== null ? (float) $variant->discount_price : null,
                'stock' => (int) $variant->stock,
            ])
            ->all();

        foreach ($images->values() as $i => $image) {
            if ($i < self::MAX_IMAGE_SLOTS && filled($image->option_name) && filled($image->option_value)) {
                $data['image_option_' . ($i + 1)] = $image->option_name . '=' . $image->option_value;
            }
        }

        return $data;
    }

    // ---------- Validation (runs before the form is saved) ----------

    public static function validate(array $state, ?Product $product = null): void
    {
        if (! filter_var($state['has_variants'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        [$options, $error] = self::inspectOptions((array) ($state['options'] ?? []));

        if ($error) {
            throw self::fail('data.options', $error);
        }

        $names = array_column($options, 'name');
        $rows = array_values((array) ($state['variants'] ?? []));

        if (! $rows) {
            throw self::fail('data.variants', 'Click "Generate combinations" to create the variant list.');
        }

        if (count($rows) > ProductVariant::MAX_PER_PRODUCT) {
            throw self::fail('data.variants', 'A product can have at most ' . ProductVariant::MAX_PER_PRODUCT . ' variants.');
        }

        $basePrice = is_numeric($state['price'] ?? null) ? (float) $state['price'] : null;
        $productSku = trim((string) ($state['sku'] ?? ''));
        $seenKeys = [];
        $seenSkus = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $combination = (array) ($row['combination'] ?? []);
            $label = self::labelOf($combination);
            $key = self::orderedKey($combination, $names);

            $stale = $key === null;

            if (! $stale) {
                foreach ($options as $option) {
                    if (! in_array((string) $combination[$option['name']], $option['choices'], true)) {
                        $stale = true;
                        break;
                    }
                }
            }

            if ($stale) {
                throw self::fail('data.variants', 'The options were changed. Click "Generate combinations" again to refresh the list.');
            }

            if (isset($seenKeys[$key])) {
                throw self::fail('data.variants', 'The combination "' . $label . '" is listed twice.');
            }

            $seenKeys[$key] = true;

            // stock
            $stock = $row['stock'] ?? null;

            if (! is_numeric($stock) || (float) $stock < 0 || floor((float) $stock) != (float) $stock) {
                throw self::fail('data.variants', 'Stock of "' . $label . '" must be a whole number, 0 or more.');
            }

            // price
            $price = $row['price'] ?? null;
            $hasPrice = $price !== null && trim((string) $price) !== '';

            if ($hasPrice && (! is_numeric($price) || (float) $price < 0)) {
                throw self::fail('data.variants', 'Price of "' . $label . '" must be a number, 0 or more.');
            }

            // discount price
            $discount = $row['discount_price'] ?? null;

            if ($discount !== null && trim((string) $discount) !== '') {
                if (! is_numeric($discount) || (float) $discount < 0) {
                    throw self::fail('data.variants', 'Discounted price of "' . $label . '" must be a number, 0 or more.');
                }

                $effective = $hasPrice ? (float) $price : $basePrice;

                if ($effective !== null && (float) $discount >= $effective) {
                    throw self::fail('data.variants', 'Discounted price of "' . $label . '" must be lower than its price.');
                }
            }

            // sku
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku === '') {
                continue;
            }

            if (! preg_match('/^[A-Za-z0-9_-]+$/', $sku) || strlen($sku) > 64) {
                throw self::fail('data.variants', 'SKU "' . $sku . '" can only have letters, numbers, dashes and underscores (max 64).');
            }

            if (isset($seenSkus[strtolower($sku)]) || ($productSku !== '' && strcasecmp($productSku, $sku) === 0)) {
                throw self::fail('data.variants', 'SKU "' . $sku . '" is used more than once.');
            }

            $seenSkus[strtolower($sku)] = true;

            $usedByOtherVariant = ProductVariant::where('sku', $sku)
                ->when($product, fn ($query) => $query->where('product_id', '!=', $product->id))
                ->exists();

            $usedByProduct = Product::where('sku', $sku)->exists();

            if ($usedByOtherVariant || $usedByProduct) {
                throw self::fail('data.variants', 'SKU "' . $sku . '" is already used by another product or variant.');
            }
        }

        // Photos tied to a choice must point to a choice that still exists
        $valid = [];

        foreach ($options as $option) {
            $valid[$option['name']] = $option['choices'];
        }

        $imageCount = count((array) ($state['image_paths'] ?? []));

        for ($i = 1; $i <= min($imageCount, self::MAX_IMAGE_SLOTS); $i++) {
            $binding = $state['image_option_' . $i] ?? null;

            if (! filled($binding)) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', (string) $binding, 2), 2, null);

            if (! isset($valid[$name]) || ! in_array((string) $value, $valid[$name], true)) {
                throw self::fail('data.image_option_' . $i, 'This choice does not exist any more. Pick another one or clear it.');
            }
        }
    }

    private static function fail(string $field, string $message): ValidationException
    {
        Notification::make()
            ->danger()
            ->title('Please check the variants')
            ->body($message)
            ->send();

        return ValidationException::withMessages([$field => $message]);
    }

    // ---------- Take the extra data out of the form data (they are not product columns) ----------

    public static function extract(array &$data): array
    {
        $bindings = [];

        for ($i = 1; $i <= self::MAX_IMAGE_SLOTS; $i++) {
            $key = 'image_option_' . $i;

            if (filled($data[$key] ?? null)) {
                $bindings[$i] = (string) $data[$key];
            }

            unset($data[$key]);
        }

        [$options] = self::inspectOptions((array) ($data['options'] ?? []));

        $extras = [
            'image_paths' => array_values((array) ($data['image_paths'] ?? [])),
            'options' => $options,
            'variants' => array_values((array) ($data['variants'] ?? [])),
            'bindings' => $bindings,
        ];

        unset($data['image_paths'], $data['options'], $data['variants']);

        return $extras;
    }

    // ---------- Save to the database ----------

    public static function persist(Product $product, array $extras): void
    {
        DB::transaction(function () use ($product, $extras) {
            $paths = array_values(array_unique(array_filter($extras['image_paths'])));

            $product->syncImages($paths);

            // Variants switched off: remove them and any photo-to-choice links
            if (! $product->has_variants) {
                ProductVariant::where('product_id', $product->id)->delete();
                ProductOption::where('product_id', $product->id)->delete();
                ProductImage::where('product_id', $product->id)->update(['option_name' => null, 'option_value' => null]);

                return;
            }

            self::syncOptions($product, $extras['options']);
            self::syncVariants($product, $extras['options'], $extras['variants']);
            self::syncBindings($product, $paths, $extras['bindings']);
        });
    }

    private static function syncOptions(Product $product, array $options): void
    {
        ProductOption::where('product_id', $product->id)->delete();

        foreach ($options as $i => $option) {
            ProductOption::create([
                'product_id' => $product->id,
                'name' => $option['name'],
                'choices' => $option['choices'],
                'sort_order' => $i + 1,
            ]);
        }
    }

    private static function syncVariants(Product $product, array $options, array $rows): void
    {
        $names = array_column($options, 'name');
        $all = ProductVariant::where('product_id', $product->id)->get();

        // Free every SKU first, so two variants can swap SKUs without a clash
        foreach ($all as $variant) {
            if ($variant->sku !== null) {
                $variant->update(['sku' => null]);
            }
        }

        $existing = [];

        foreach ($all as $variant) {
            $key = self::orderedKey((array) $variant->combination, $names);

            if ($key !== null) {
                $existing[$key] = $variant;
            }
        }

        $keep = [];

        foreach (array_values($rows) as $i => $row) {
            $row = (array) $row;
            $source = (array) ($row['combination'] ?? []);

            $combination = [];
            foreach ($names as $name) {
                $combination[$name] = (string) $source[$name];
            }

            $key = ProductVariant::makeKey($combination);
            $sku = trim((string) ($row['sku'] ?? ''));

            $attributes = [
                'combination' => $combination,
                'combination_key' => $key,
                'sku' => $sku !== '' ? $sku : null,
                'price' => filled($row['price'] ?? null) ? round((float) $row['price'], 2) : null,
                'discount_price' => filled($row['discount_price'] ?? null) ? round((float) $row['discount_price'], 2) : null,
                'stock' => max(0, (int) ($row['stock'] ?? 0)),
                'sort_order' => $i + 1,
            ];

            if (isset($existing[$key])) {
                $existing[$key]->update($attributes);
                $keep[] = $existing[$key]->id;
            } else {
                $keep[] = ProductVariant::create($attributes + ['product_id' => $product->id])->id;
            }
        }

        ProductVariant::where('product_id', $product->id)->whereNotIn('id', $keep)->delete();
    }

    private static function syncBindings(Product $product, array $paths, array $bindings): void
    {
        ProductImage::where('product_id', $product->id)->update(['option_name' => null, 'option_value' => null]);

        foreach ($bindings as $position => $binding) {
            $path = $paths[$position - 1] ?? null;

            if (! $path) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $binding, 2), 2, null);

            if (blank($name) || blank($value)) {
                continue;
            }

            ProductImage::where('product_id', $product->id)
                ->where('path', $path)
                ->update(['option_name' => $name, 'option_value' => $value]);
        }
    }
}