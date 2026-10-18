@props(['hero'])
@php
    $eyebrow = trim((string) ($hero['hero_eyebrow'] ?? ''));
    $title = trim((string) ($hero['hero_title'] ?? ''));
    $text = trim((string) ($hero['hero_text'] ?? ''));

    $badges = [];
    foreach (['hero_badge_1' => 'diamond', 'hero_badge_2' => 'truck', 'hero_badge_3' => 'heart'] as $key => $icon) {
        $label = trim((string) ($hero[$key] ?? ''));
        if ($label !== '') {
            $badges[] = ['icon' => $icon, 'label' => $label];
        }
    }
@endphp

{{-- Text block (left side). Only the button takes clicks, so dragging the slider still works --}}
<div class="wrap pointer-events-none absolute inset-0 flex flex-col justify-center pb-36 pt-24 lg:pb-32 lg:pt-28">
    <div class="max-w-xl lg:max-w-2xl">
        @if ($eyebrow !== '')
            <p class="text-[11px] uppercase tracking-[0.3em] text-cocoa/80 sm:text-xs">{{ $eyebrow }}</p>
        @endif

        @if ($title !== '')
            <h1 class="mt-5 whitespace-pre-line font-serif text-5xl font-medium leading-[1.05] text-cocoa sm:text-6xl lg:text-7xl">{{ $title }}</h1>
        @endif

        @if ($text !== '')
            <p class="mt-6 max-w-md whitespace-pre-line text-base leading-relaxed text-cocoa/85">{{ $text }}</p>
        @endif

        <a href="#collections" draggable="false"
           class="pointer-events-auto mt-9 inline-flex items-center gap-4 border border-cocoa bg-cocoa px-8 py-4 text-xs font-medium uppercase tracking-[0.3em] text-ivory transition duration-500 hover:bg-transparent hover:text-cocoa">
            {{ __('Shop Now') }}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h18M15 6l6 6-6 6"/></svg>
        </a>
    </div>
</div>

{{-- Small badges (bottom-left) --}}
@if (count($badges))
    <div class="wrap pointer-events-none absolute inset-x-0 bottom-14 lg:bottom-16">
        <ul class="flex items-start divide-x divide-cocoa/20">
            @foreach ($badges as $badge)
                <li class="flex max-w-[8.5rem] flex-col items-center gap-2 px-3 text-center first:pl-0 sm:px-7">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-cocoa" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @if ($badge['icon'] === 'diamond')
                            <path d="M6 3h12l3 6-9 12L3 9l3-6z"/>
                            <path d="M3 9h18M9 3l3 6 3-6M12 21L9 9M12 21l3-12"/>
                        @elseif ($badge['icon'] === 'truck')
                            <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/>
                            <circle cx="7" cy="17.5" r="1.8"/>
                            <circle cx="17" cy="17.5" r="1.8"/>
                        @else
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        @endif
                    </svg>
                    <span class="text-[11px] leading-snug text-cocoa/80 sm:text-xs">{{ $badge['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif