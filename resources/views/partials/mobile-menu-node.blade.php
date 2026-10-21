@php($hasKids = ! empty($node['children']))
<li class="border-b border-taupe/20" @if ($hasKids) x-data="{ expanded: false }" @endif>
    <div class="flex items-center justify-between">
        <a href="{{ route('category.show', $node['slug']) }}"
           class="block flex-1 py-4 text-cocoa"
           style="padding-left: {{ $level * 1.25 }}rem">{{ $node['name'] }}</a>

        @if ($hasKids)
            <button type="button" @click="expanded = !expanded" :aria-expanded="expanded"
                    aria-label="{{ __('Show sub-categories') }}" class="p-3 text-cocoa">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-300" :class="expanded ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
            </button>
        @endif
    </div>

    @if ($hasKids)
        <ul x-show="expanded" x-cloak>
            @foreach ($node['children'] as $child)
                @include('partials.mobile-menu-node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </ul>
    @endif
</li>