<x-filament-panels::page>
    <style>
        .ct-scroll { overflow-x: auto; }
        .ct-grid {
            display: grid;
            grid-template-columns: 2rem 3.25rem minmax(14rem, 1fr) 6.5rem 5.5rem 5rem 5rem 8.5rem;
            align-items: center;
            gap: 0.5rem;
            min-width: 58rem;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid rgba(128, 128, 128, 0.2);
        }
        .ct-head { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; opacity: 0.65; }
        .ct-row:hover { background: rgba(128, 128, 128, 0.07); }
        .ct-img { display: block; width: 2.75rem; height: 2.75rem; border-radius: 0.375rem; object-fit: cover; background: rgba(128, 128, 128, 0.15); }
        .ct-arrow { transition: transform 0.15s; }
        .ct-open .ct-arrow { transform: rotate(90deg); }
        .ct-handle { cursor: grab; opacity: 0.55; display: inline-flex; }
        .ct-handle:active { cursor: grabbing; }
        .ct-dragging { opacity: 0.45; }
        .ct-actions { display: flex; justify-content: flex-end; align-items: center; gap: 0.125rem; }
        .ct-muted { opacity: 0.4; }
        .ct-center { text-align: center; }
    </style>

    @if (empty($tree))
        <x-filament::section>
            <p>No categories yet. Click "New category" to add the first one.</p>
        </x-filament::section>
    @else
        <p style="font-size: 0.8rem; opacity: 0.7;">
            Click the arrow to open sub-categories. Drag the handle (&#8942;&#8942;) to reorder main categories. Sub-categories are always shown A-Z. Use "+" to add a sub-category inside a row.
        </p>

        <x-filament::section :compact="true">
            <div class="ct-scroll"
                 @mouseup.window="if (drag === null) canDrag = false"
                 x-data="{
                    open: {},
                    drag: null,
                    over: null,
                    canDrag: false,
                    reset() { this.drag = null; this.over = null; this.canDrag = false; },
                    dropOn(target) {
                        const from = this.drag;
                        if (from === null || from === target) { this.reset(); return; }
                        const before = [...this.$root.querySelectorAll('[data-top]')].map((el) => Number(el.dataset.top));
                        const ids = before.filter((id) => id !== from);
                        const at = ids.indexOf(target);
                        ids.splice(before.indexOf(from) < before.indexOf(target) ? at + 1 : at, 0, from);
                        this.reset();
                        this.$wire.reorderTop(ids);
                    }
                 }">

                <div class="ct-grid ct-head">
                    <div></div>
                    <div>Image</div>
                    <div>Name</div>
                    <div class="ct-center">Sub-categories</div>
                    <div class="ct-center">Products</div>
                    <div class="ct-center">Sort order</div>
                    <div class="ct-center">In navbar</div>
                    <div style="text-align: right;">Actions</div>
                </div>

                @foreach ($tree as $node)
                    @include('filament.resources.categories.partials.category-node', ['node' => $node])
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>