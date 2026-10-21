@php
    $slides = array_map(fn ($path) => asset('storage/' . $path), $heroPaths);
@endphp
<x-store-layout :description="__('Elegant jewellery and accessories for women.')" :overlay="true">

    {{-- Hero: starts right under the top strip; the round menu floats on top of it.
         Its height comes from the content, so nothing can overlap. --}}
    @if (count($slides))
        <section class="relative w-full touch-pan-y select-none overflow-hidden bg-beige"
                 x-data="heroSlider({{ count($slides) }})"
                 @pointerdown="down($event)"
                 @pointermove.window="move($event)"
                 @pointerup.window="up()"
                 @pointercancel.window="up()"
                 @click.capture="guard($event)"
                 @pointerenter="if ($event.pointerType === 'mouse') paused = true"
                 @pointerleave="paused = false"
                 :class="n > 1 ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : ''">

            <div class="absolute inset-0 flex"
                 :class="dragging ? '' : 'transition-transform duration-[900ms] ease-[cubic-bezier(0.22,0.7,0.2,1)]'"
                 :style="trackStyle">
                @foreach ($slides as $url)
                    <img src="{{ $url }}" alt="Feminique" draggable="false"
                         @if ($loop->first) fetchpriority="high" @elseif ($loop->index > 1) loading="lazy" @endif
                         class="h-full w-full shrink-0 object-cover object-[72%_center] lg:object-center">
                @endforeach
            </div>

            {{-- Soft light veil so the dark text stays readable --}}
            <div class="pointer-events-none absolute inset-0 bg-linear-to-t from-ivory/85 via-ivory/50 to-transparent lg:bg-linear-to-r lg:from-ivory/75 lg:via-ivory/25 lg:to-transparent"></div>

            {{-- Light veil behind the menu --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-linear-to-b from-ivory/60 to-transparent"></div>

            <x-hero-content :hero="$hero" />

            {{-- Line indicators (bottom centre) --}}
            @if (count($slides) > 1)
                <div class="absolute inset-x-0 bottom-4 z-20 flex items-center justify-center gap-2 lg:bottom-6" @pointerdown.stop>
                    <template x-for="k in n" :key="k">
                        <button type="button" @click="go(k - 1)" :aria-label="'Slide ' + k" class="flex h-8 items-center">
                            <span class="relative block h-[0.125rem] overflow-hidden bg-cocoa/25 transition-all duration-500"
                                  :class="i === k - 1 ? 'w-16' : 'w-8'">
                                <span class="absolute inset-y-0 left-0 block bg-cocoa transition-[width] duration-100 ease-linear"
                                      :style="{ width: (i === k - 1 ? progress : 0) + '%' }"></span>
                            </span>
                        </button>
                    </template>
                </div>
            @endif
        </section>
    @else
        <section class="relative w-full overflow-hidden bg-beige">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-linear-to-b from-ivory/60 to-transparent"></div>
            <x-hero-content :hero="$hero" />
        </section>
    @endif

    {{-- Categories (main categories only; one row, drag or swipe, endless loop) --}}
    @if ($categories->isNotEmpty())
        <x-category-scroller :categories="$categories" id="collections"
                             :eyebrow="__('Collections')" :title="__('Shop by Category')" />
    @endif

    {{-- New Arrivals --}}
    @if ($newArrivals->isNotEmpty())
        <section id="new-arrivals" class="wrap pb-8">
            <div class="text-center" data-reveal>
                <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Just in') }}</p>
                <h2 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('New Arrivals') }}</h2>
            </div>

            <div class="mt-14 grid grid-cols-2 gap-x-5 gap-y-12 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-8 2xl:grid-cols-5">
                @foreach ($newArrivals as $product)
                    <x-product-card :product="$product" :delay="($loop->index % 5) * 80" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Customer Favorites: best sellers of the last 90 days (filled with newest products) --}}
    @if ($popular->isNotEmpty())
        <section id="customer-favorites" class="wrap pb-8 pt-20">
            <div class="text-center" data-reveal>
                <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Most loved') }}</p>
                <h2 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('Customer Favorites') }}</h2>
            </div>

            <div class="mt-14 grid grid-cols-2 gap-x-5 gap-y-12 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-8 2xl:grid-cols-5">
                @foreach ($popular as $product)
                    <x-product-card :product="$product" :delay="($loop->index % 5) * 80" />
                @endforeach
            </div>
        </section>
    @endif

</x-store-layout>