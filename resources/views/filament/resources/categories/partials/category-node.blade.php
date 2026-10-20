@use('App\Filament\Resources\Categories\CategoryResource')
@use('App\Models\Category')
@php
    $id = $node['id'];
    $depth = $node['depth'];
    $hasChildren = $node['children_count'] > 0;
@endphp

<div wire:key="cat-{{ $id }}"
     @if ($depth === 1)
         data-top="{{ $id }}"
         :draggable="canDrag"
         @dragstart="drag = {{ $id }}; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $id }}')"
         @dragover.prevent="over = {{ $id }}"
         @drop.prevent="dropOn({{ $id }})"
         @dragend="reset()"
         :class="drag === {{ $id }} && 'ct-dragging'"
         :style="(drag !== null && over === {{ $id }} && drag !== {{ $id }}) ? 'box-shadow: inset 0 3px 0 0 #f59e0b' : ''"
     @endif>

    <div class="ct-grid ct-row" :class="open[{{ $id }}] && 'ct-open'">

        {{-- Drag handle (main categories only) --}}
        <div>
            @if ($depth === 1)
                <span class="ct-handle" @mousedown="canDrag = true" title="Drag to reorder">
                    <x-filament::icon icon="heroicon-o-bars-3" style="width: 1.25rem; height: 1.25rem;" />
                </span>
            @endif
        </div>

        {{-- Image --}}
        <div>
            @if ($node['image'])
                <img class="ct-img" src="{{ asset('storage/' . $node['image']) }}" alt="" loading="lazy" draggable="false">
            @else
                <div class="ct-img"></div>
            @endif
        </div>

        {{-- Name (indented by level) + open/close arrow --}}
        <div style="display: flex; align-items: center; gap: 0.25rem; padding-left: {{ ($depth - 1) * 1.75 }}rem; min-width: 0;">
            @if ($hasChildren)
                <button type="button" @click="open[{{ $id }}] = ! open[{{ $id }}]" aria-label="Show sub-categories"
                        style="display: inline-flex; padding: 0.25rem; flex-shrink: 0;">
                    <x-filament::icon icon="heroicon-o-chevron-right" class="ct-arrow" style="width: 1rem; height: 1rem;" />
                </button>
            @else
                <span style="display: inline-block; width: 1.5rem; flex-shrink: 0;"></span>
            @endif

            <div style="min-width: 0;">
                <div style="font-weight: {{ $depth === 1 ? 600 : 500 }}; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $node['name'] }}</div>
                <div style="font-size: 0.7rem; opacity: 0.55;">{{ $node['slug'] }}</div>
            </div>
        </div>

        {{-- Sub-categories --}}
        <div class="ct-center">
            @if ($hasChildren)
                <x-filament::badge color="gray">{{ $node['children_count'] }}</x-filament::badge>
            @else
                <span class="ct-muted">-</span>
            @endif
        </div>

        {{-- Products (directly in this category) --}}
        <div class="ct-center">{{ $node['products_count'] }}</div>

        {{-- Sort order (main categories only) --}}
        <div class="ct-center">
            @if ($depth === 1)
                {{ $node['sort_order'] }}
            @else
                <span class="ct-muted">-</span>
            @endif
        </div>

        {{-- In navbar (main categories only) --}}
        <div class="ct-center">
            @if ($depth === 1)
                <x-filament::icon
                    :icon="$node['show_in_navbar'] ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'"
                    style="width: 1.25rem; height: 1.25rem; display: inline-block; color: {{ $node['show_in_navbar'] ? '#16a34a' : '#9ca3af' }};" />
            @else
                <span class="ct-muted">-</span>
            @endif
        </div>

        {{-- Actions: add sub-category, edit, delete --}}
        <div class="ct-actions">
            @if ($depth < Category::MAX_DEPTH)
                <x-filament::icon-button
                    icon="heroicon-o-plus"
                    tag="a"
                    :href="CategoryResource::getUrl('create') . '?parent=' . $id"
                    color="success"
                    size="sm"
                    label="Add sub-category" />
            @else
                <span style="display: inline-block; width: 2rem;"></span>
            @endif

            <x-filament::icon-button
                icon="heroicon-o-pencil-square"
                tag="a"
                :href="CategoryResource::getUrl('edit', ['record' => $id])"
                color="primary"
                size="sm"
                label="Edit" />

            <x-filament::icon-button
                icon="heroicon-o-trash"
                color="danger"
                size="sm"
                label="Delete"
                wire:click="deleteCategory({{ $id }})"
                wire:confirm="Delete this category? This cannot be undone." />
        </div>
    </div>

    @if ($hasChildren)
        <div x-show="open[{{ $id }}]" x-cloak>
            @foreach ($node['children'] as $child)
                @include('filament.resources.categories.partials.category-node', ['node' => $child])
            @endforeach
        </div>
    @endif
</div>