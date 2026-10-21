@props(['categories', 'eyebrow' => null, 'title' => null, 'id' => null])
@php
    $arrowButton = 'flex h-11 w-11 items-center justify-center border transition-colors duration-300 disabled:cursor-default disabled:opacity-30';
@endphp
{{-- One row of categories: drag or swipe, endless loop (script: dragScroll in app.js).
     Each card is EXACTLY one column of the product grid (2 / 4 / 5 / 6 columns, same gaps),
     so category cards and product cards always have the same size.
     Without a title (sub-category pages) the arrows float above the row and take no space. --}}
<section @if ($id) id="{{ $id }}" @endif
         class="wrap scroll-mt-24 {{ $title ? 'py-24' : 'pb-2 pt-0' }}" x-data="dragScroll">
    <div class="relative {{ $title ? 'text-center' : 'h-0' }}" @if ($title) data-reveal @endif>
        @if ($eyebrow)
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ $eyebrow }}</p>
        @endif
        @if ($title)
            <h2 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ $title }}</h2>
        @endif

        <div class="absolute right-0 hidden gap-3 lg:flex {{ $title ? 'bottom-0' : 'bottom-3' }}" x-show="thumbW < 99.5">
            <button type="button" @click="by(-1)" :disabled="!canLeft" aria-label="{{ __('Previous') }}" class="{{ $arrowButton }} border-taupe/60 text-cocoa hover:bg-cocoa hover:text-ivory">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
            </button>
            <button type="button" @click="by(1)" :disabled="!canRight" aria-label="{{ __('Next') }}" class="{{ $arrowButton }} border-taupe/60 text-cocoa hover:bg-cocoa hover:text-ivory">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
            </button>
        </div>
    </div>

    <div x-ref="track"
         @scroll.passive="update()"
         @mousedown="down($event)"
         @mousemove.window="move($event)"
         @mouseup.window="up()"
         @click.capture="guard($event)"
         :class="dragging ? 'cursor-grabbing snap-none' : 'cursor-grab snap-x snap-proximity'"
         class="relative {{ $title ? 'mt-14' : 'mt-3' }} flex select-none gap-5 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden lg:gap-8">
        @foreach ($categories as $category)
            <a href="{{ route('category.show', $category->slug) }}" draggable="false" data-cat
               class="group block w-[calc((100%_-_1.25rem)/2)] shrink-0 snap-start md:w-[calc((100%_-_3.75rem)/4)] lg:w-[calc((100%_-_8rem)/5)] 2xl:w-[calc((100%_-_10rem)/6)]">
                <div class="aspect-[4/5] overflow-hidden rounded-xl bg-beige">
                    @if ($category->image)
                        <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}" loading="lazy" draggable="false"
                             class="h-full w-full object-cover transition duration-[1400ms] ease-out group-hover:scale-[1.05]">
                    @else
                        <div class="flex h-full w-full items-center justify-center">
                            <span class="font-serif text-lg uppercase tracking-[0.35em] text-cocoa/40">Feminique</span>
                        </div>
                    @endif
                </div>
                <div class="mt-4">
                    <span class="nav-link font-serif text-2xl leading-tight lg:text-[1.65rem]">{{ $category->name }}</span>
                </div>
            </a>
        @endforeach
    </div>
</section>