@props(['ad'])
@php
    $link = $ad->safeLink();
    $kind = $ad->kind();
    $title = trim((string) $ad->title);
    $text = trim((string) $ad->text);
    $button = $link ? trim((string) $ad->button_text) : '';
    $hasText = $title !== '' || $text !== '' || $button !== '';

    $dark = $ad->text_color === 'dark';
    $align = in_array($ad->text_align, ['left', 'center', 'right'], true) ? $ad->text_align : 'left';

    $textClass = $dark ? 'text-cocoa' : 'text-ivory';
    $buttonClass = $dark ? 'border-cocoa bg-cocoa text-ivory' : 'border-ivory bg-ivory text-cocoa';
    $veil = match (true) {
        $align === 'center' && $dark => 'bg-ivory/65',
        $align === 'center' => 'bg-cocoa/50',
        $align === 'right' && $dark => 'bg-linear-to-l from-ivory/90 via-ivory/60 to-transparent',
        $align === 'right' => 'bg-linear-to-l from-cocoa/85 via-cocoa/55 to-transparent',
        $dark => 'bg-linear-to-r from-ivory/90 via-ivory/60 to-transparent',
        default => 'bg-linear-to-r from-cocoa/85 via-cocoa/55 to-transparent',
    };
    $alignClass = match ($align) {
        'right' => 'items-end text-right',
        'center' => 'items-center text-center',
        default => 'items-start text-left',
    };
    $justify = match ($align) {
        'right' => 'justify-end',
        'center' => 'justify-center',
        default => 'justify-start',
    };
@endphp

{{-- One ad. Its height is the same as a product / category card picture (see .ad-frame in app.css). --}}
<section class="wrap ad-wrap py-8 lg:py-10" data-reveal>
    <div class="ad-frame relative isolate flex overflow-hidden rounded-2xl bg-beige {{ $textClass }}">

        @if ($kind === 'video')
            <video src="{{ asset('storage/' . $ad->media) }}"
                   @if ($ad->poster) poster="{{ asset('storage/' . $ad->poster) }}" @endif
                   autoplay muted loop playsinline preload="metadata" disablepictureinpicture aria-hidden="true"
                   x-data x-init="if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { $el.pause(); $el.removeAttribute('autoplay'); }"
                   class="absolute inset-0 h-full w-full object-cover"></video>
        @else
            <picture>
                @if ($ad->mobile_media)
                    <source media="(max-width: 47.99rem)" srcset="{{ asset('storage/' . $ad->mobile_media) }}">
                @endif
                <img src="{{ asset('storage/' . $ad->media) }}" alt="" loading="lazy" draggable="false"
                     class="absolute inset-0 h-full w-full object-cover">
            </picture>
        @endif

        @if ($hasText)
            <div class="absolute inset-0 {{ $veil }}"></div>

            <div class="relative z-10 flex w-full {{ $justify }}">
                <div class="flex w-full max-w-2xl flex-col justify-center gap-3 px-6 py-6 sm:px-10 lg:px-14 {{ $alignClass }}">
                    @if ($title !== '')
                        <h2 class="font-serif text-2xl font-light leading-tight sm:text-3xl lg:text-4xl">{{ $title }}</h2>
                    @endif

                    @if ($text !== '')
                        <p class="whitespace-pre-line text-sm leading-relaxed sm:text-base">{{ $text }}</p>
                    @endif

                    @if ($button !== '')
                        <span class="mt-1 inline-flex items-center gap-3 rounded-full border px-6 py-2.5 text-xs font-medium uppercase tracking-[0.25em] {{ $buttonClass }}">
                            {{ $button }}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h18M15 6l6 6-6 6"/></svg>
                        </span>
                    @endif
                </div>
            </div>
        @endif

        {{-- The whole ad is clickable (one link on top, so the button is not a second link) --}}
        @if ($link)
            <a href="{{ $link }}" class="absolute inset-0 z-20"
               aria-label="{{ $title !== '' ? $title : $ad->name }}"
               @if (preg_match('#^https?://#i', $link)) rel="noopener noreferrer" @endif></a>
        @endif
    </div>
</section>