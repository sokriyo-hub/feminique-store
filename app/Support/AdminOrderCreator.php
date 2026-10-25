<?php

namespace App\Support;

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * An order typed in by the admin (Order desk): phone, Facebook, WhatsApp, walk-in ...
 * Stock is taken exactly like a website order (rows locked, checked, then reduced).
 */
class AdminOrderCreator
{
    public const MAX_QTY = 10000;

    /**
     * $data: name, phone, email, address, source, status, zone_id, custom_charge, discount,
     *        lines = [ ['product_id', 'variant_id', 'quantity', 'unit_price'], ... ]
     *
     * Returns [Order|null, errorMessage|null]. When there is an error NOTHING is saved.
     */
    public static function create(array $data): array
    {
        $phone = Phone::normalize((string) ($data['phone'] ?? ''));

        if ($phone === null) {
            return [null, 'Enter a valid mobile number, like 01712345678.'];
        }

        $zone = DeliveryZone::where('is_active', true)->find((int) ($data['zone_id'] ?? 0));

        if (! $zone) {
            return [null, 'Choose an active delivery zone.'];
        }

        $lines = [];

        foreach (array_values((array) ($data['lines'] ?? [])) as $line) {
            $line = (array) $line;
            $quantity = (int) ($line['quantity'] ?? 0);
            $price = $line['unit_price'] ?? null;

            if ((int) ($line['product_id'] ?? 0) <= 0 || $quantity < 1 || $quantity > self::MAX_QTY) {
                return [null, 'Every line needs a product and a quantity from 1 to ' . self::MAX_QTY . '.'];
            }

            if (! is_numeric($price) || (float) $price < 0) {
                return [null, 'Every line needs a unit price (0 or more).'];
            }

            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'variant_id' => (int) ($line['variant_id'] ?? 0),
                'quantity' => $quantity,
                'unit' => round((float) $price, 2),
            ];
        }

        if (! $lines) {
            return [null, 'Add at least one product.'];
        }

        $discount = round(max(0, (float) ($data['discount'] ?? 0)), 2);
        $custom = $data['custom_charge'] ?? null;
        $hasCustomCharge = $custom !== null && $custom !== '' && is_numeric($custom);

        $status = in_array($data['status'] ?? '', array_keys(Order::deskStatusOptions()), true) ? $data['status'] : 'confirmed';
        $source = array_key_exists($data['source'] ?? '', Order::deskSourceOptions()) ? $data['source'] : 'other';

        return DB::transaction(function () use ($data, $phone, $zone, $lines, $discount, $custom, $hasCustomCharge, $status, $source): array {
            // Lock rows always in the same order (by id), like checkout does
            $products = Product::whereIn('id', array_unique(array_column($lines, 'product_id')))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $variantIds = array_values(array_filter(array_unique(array_column($lines, 'variant_id'))));

            $variants = $variantIds
                ? ProductVariant::whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id')
                : collect();

            $rows = [];
            $need = [];

            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);

                if (! $product) {
                    return [null, 'A product was not found. It may have been deleted.'];
                }

                $variant = null;

                if ($product->has_variants) {
                    $variant = $variants->get($line['variant_id']);

                    if (! $variant || (int) $variant->product_id !== (int) $product->id) {
                        return [null, '"' . $product->name . '" needs a variant (for example Size or Color).'];
                    }
                } elseif ($line['variant_id'] !== 0) {
                    return [null, '"' . $product->name . '" has no variants.'];
                }

                $key = $product->id . '-' . ($variant?->id ?? 0);
                $need[$key] = ($need[$key] ?? 0) + $line['quantity'];

                $rows[] = [
                    'key' => $key,
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'regular' => $product->linePrices($variant)[0],
                    'total' => round($line['unit'] * $line['quantity'], 2),
                ];
            }

            // Stock check (the same product on two lines is added together)
            foreach ($rows as $row) {
                $available = $row['product']->stockFor($row['variant']);

                if ($available < $need[$row['key']]) {
                    $name = $row['variant'] ? $row['product']->name . ' (' . $row['variant']->label() . ')' : $row['product']->name;

                    return [null, 'Not enough stock for ' . $name . ': needs ' . $need[$row['key']] . ', only ' . $available . ' left.'];
                }
            }

            $subtotal = round((float) array_sum(array_column($rows, 'total')), 2);

            if ($discount > $subtotal) {
                return [null, 'The discount cannot be more than the items total (Tk ' . number_format($subtotal, 2) . ').'];
            }

            $charge = $hasCustomCharge
                ? round(max(0, (float) $custom), 2)
                : Delivery::charge($zone, $subtotal);

            $order = Order::create([
                'code' => Order::generateCode(),
                'customer_name' => trim((string) $data['name']),
                'phone' => $phone,
                'email' => filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
                'address' => trim((string) $data['address']),
                'delivery_zone_name' => $zone->name,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_charge' => $charge,
                'total' => round($subtotal - $discount + $charge, 2),
                'status' => $status,
                'source' => $source,
                'created_by_admin' => true,
                'stock_deducted' => true,
            ]);

            foreach ($rows as $row) {
                $product = $row['product'];
                $variant = $row['variant'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_label' => $variant?->label(),
                    'sku' => $variant?->sku ?? $product->sku,
                    'regular_price' => $row['regular'],
                    'unit_price' => $row['unit'],
                    'quantity' => $row['quantity'],
                    'line_total' => $row['total'],
                ]);

                if ($variant) {
                    $variant->decrement('stock', $row['quantity']);
                } else {
                    $product->decrement('stock', $row['quantity']);
                }
            }

            return [$order, null];
        });
    }
}