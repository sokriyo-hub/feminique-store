<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryTree
{
    // Main categories: by sort_order (then name). Sub and sub-sub categories: A-Z.
    private static function groupSorted(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn ($c) => $c->parent_id === null ? 0 : (int) $c->parent_id)
            ->map(function (Collection $group, $parent) {
                $sorted = (int) $parent === 0
                    ? $group->sort(fn ($a, $b) => ((int) $a->sort_order <=> (int) $b->sort_order) ?: strnatcasecmp($a->name, $b->name))
                    : $group->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name));

                return $sorted->values();
            });
    }

    /**
     * Every category in tree order (parent, then its children ...).
     * Each row: id, name, path ("Rings > Gold Rings"), segments, depth (1 = top), parent_id
     */
    public static function flat(): array
    {
        $rows = Category::get(['id', 'name', 'parent_id', 'sort_order']);
        $byParent = self::groupSorted($rows);

        $out = [];
        $walk = function (int $parent, array $segments, int $depth) use (&$walk, $byParent, &$out): void {
            if ($depth > 10) {
                return;
            }

            foreach ($byParent->get($parent, collect()) as $c) {
                $names = array_merge($segments, [$c->name]);

                $out[] = [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'path' => implode(' > ', $names),
                    'segments' => $names,
                    'depth' => $depth,
                    'parent_id' => $c->parent_id === null ? null : (int) $c->parent_id,
                ];

                $walk((int) $c->id, $names, $depth + 1);
            }
        };

        $walk(0, [], 1);

        return $out;
    }

    // Nested tree for the admin list (with counts)
    public static function tree(): array
    {
        $rows = Category::withCount(['children', 'products'])->get();
        $byParent = self::groupSorted($rows);

        $build = function (int $parent, int $depth) use (&$build, $byParent): array {
            if ($depth > 10) {
                return [];
            }

            $nodes = [];

            foreach ($byParent->get($parent, collect()) as $c) {
                $nodes[] = [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'image' => $c->image,
                    'sort_order' => (int) $c->sort_order,
                    'show_in_navbar' => (bool) $c->show_in_navbar,
                    'children_count' => (int) $c->children_count,
                    'products_count' => (int) $c->products_count,
                    'depth' => $depth,
                    'children' => $build((int) $c->id, $depth + 1),
                ];
            }

            return $nodes;
        };

        return $build(0, 1);
    }

    /**
     * Nested menu for the storefront (one query for the whole site header).
     * Each node: id, name, slug, navbar (show in top menu?), children[]
     * Main categories by sort_order, sub-categories A-Z. Goes down Category::MAX_DEPTH levels.
     */
    public static function menu(): array
    {
        $rows = Category::get(['id', 'name', 'slug', 'parent_id', 'sort_order', 'show_in_navbar']);
        $byParent = self::groupSorted($rows);

        $build = function (int $parent, int $depth) use (&$build, $byParent): array {
            if ($depth > Category::MAX_DEPTH) {
                return [];
            }

            $nodes = [];

            foreach ($byParent->get($parent, collect()) as $c) {
                $nodes[] = [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'navbar' => (bool) $c->show_in_navbar,
                    'children' => $build((int) $c->id, $depth + 1),
                ];
            }

            return $nodes;
        };

        return $build(0, 1);
    }

    // Dropdown options sorted A-Z by path, level by level: "Rings", "Rings > Gold Rings", "Rings > Silver Rings", "Necklaces" ...
    private static function optionsFrom(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $n = min(count($a['segments']), count($b['segments']));

            for ($i = 0; $i < $n; $i++) {
                $c = strnatcasecmp($a['segments'][$i], $b['segments'][$i]);

                if ($c !== 0) {
                    return $c;
                }
            }

            return count($a['segments']) <=> count($b['segments']);
        });

        $options = [];

        foreach ($rows as $row) {
            $options[$row['id']] = $row['path'];
        }

        return $options;
    }

    // [id => "Rings > Gold Rings"] sorted A-Z (use this for EVERY category dropdown)
    public static function pathOptions(): array
    {
        return self::optionsFrom(self::flat());
    }

    // ids of everything below a category (children, grandchildren ...)
    public static function descendantIds(int $id, array $flat): array
    {
        $all = [];
        $current = [$id];
        $guard = 0;

        while ($current && $guard++ < 20) {
            $next = [];

            foreach ($flat as $row) {
                if ($row['parent_id'] !== null && in_array($row['parent_id'], $current, true)) {
                    $next[] = $row['id'];
                }
            }

            $all = array_merge($all, $next);
            $current = $next;
        }

        return $all;
    }

    /**
     * Categories that may become the parent of $recordId (null = a new category):
     * not itself, not its own children, and the whole branch must stay within MAX_DEPTH.
     * Sorted A-Z.
     */
    public static function validParents(?int $recordId): array
    {
        $flat = self::flat();
        $exclude = [];
        $height = 0; // how many levels the record's own subtree already has below it

        if ($recordId) {
            $descendants = self::descendantIds($recordId, $flat);
            $exclude = array_merge([$recordId], $descendants);

            $byId = [];
            foreach ($flat as $row) {
                $byId[$row['id']] = $row;
            }

            if (isset($byId[$recordId])) {
                foreach ($descendants as $d) {
                    $height = max($height, $byId[$d]['depth'] - $byId[$recordId]['depth']);
                }
            }
        }

        $rows = [];

        foreach ($flat as $row) {
            if (in_array($row['id'], $exclude, true)) {
                continue;
            }

            if ($row['depth'] + 1 + $height > Category::MAX_DEPTH) {
                continue;
            }

            $rows[] = $row;
        }

        return self::optionsFrom($rows);
    }
}