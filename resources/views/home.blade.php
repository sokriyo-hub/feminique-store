@php
    $slides = array_map(fn ($path) => asset('storage/' . $path), $heroPaths);
    $arrowButton = 'flex h-11 w-11 items-center justify-center border transition-colors duration-300 disabled:cursor-default disabled:opacity-30';
@endphp
<x-store-layout :description="__('Elegant jewellery and accessories for women.')">

    {{-- Hero --}}
    @if (count($slides))
        <section class="relative h-[78vh] min-h-[460px] w-full touch-pan-y select-none overflow-hidden bg-beige"
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
                         class="h-full w-full shrink-0 object-cover">
                @endforeach
            </div>

            <div class="pointer-events-none absolute inset-0 bg-cocoa/25"></div>

            {{-- Shop Now (bottom-left) --}}
            <div class="wrap absolute inset-x-0 bottom-14 lg:bottom-16">
                <a href="#collections" draggable="false" class="inline-block border border-ivory bg-transparent px-10 py-4 text-xs font-medium uppercase tracking-[0.3em] text-ivory transition duration-500 hover:bg-ivory hover:text-cocoa">{{ __('Shop Now') }}</a>
            </div>

            {{-- Line indicators (bottom centre) --}}
            @if (count($slides) > 1)
                <div class="absolute inset-x-0 bottom-4 flex items-center justify-center gap-2 lg:bottom-6" @pointerdown.stop>
                    <template x-for="k in n" :key="k">
                        <button type="button" @click="go(k - 1)" :aria-label="'Slide ' + k" class="flex h-8 items-center">
                            <span class="relative block h-[2px] overflow-hidden bg-ivory/40 transition-all duration-500"
                                  :class="i === k - 1 ? 'w-16' : 'w-8'">
                                <span class="absolute inset-y-0 left-0 block bg-ivory transition-[width] duration-100 ease-linear"
                                      :style="{ width: (i === k - 1 ? progress : 0) + '%' }"></span>
                            </span>
                        </button>
                    </template>
                </div>
            @endif
        </section>
    @else
        <section class="relative flex h-[60vh] min-h-[380px] w-full items-center justify-center bg-beige px-6 text-center">
            <p class="font-serif text-5xl font-light uppercase tracking-[0.3em] text-cocoa lg:text-7xl">Feminique</p>
            <div class="wrap absolute inset-x-0 bottom-14 text-left lg:bottom-16">
                <a href="#collections" class="inline-block border border-cocoa bg-transparent px-10 py-4 text-xs font-medium uppercase tracking-[0.3em] text-cocoa transition duration-500 hover:bg-cocoa hover:text-ivory">{{ __('Shop Now') }}</a>
            </div>
        </section>
    @endif

    {{-- White area: from below the hero down to the footer (the negative bottom margin covers the footer's top gap) --}}
    <div class="-mb-24 bg-white pb-24">

        {{-- Categories (one row, drag or swipe, endless loop, no limit on the number) --}}
        @if ($categories->isNotEmpty())
            <section id="collections" class="wrap scroll-mt-24 py-24" x-data="dragScroll">
                <div class="relative text-center" data-reveal>
                    <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Collections') }}</p>
                    <h2 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('Shop by Category') }}</h2>

                    <div class="absolute bottom-0 right-0 hidden gap-3 lg:flex" x-show="thumbW < 99.5">
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
                     class="relative mt-14 flex select-none gap-5 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden lg:gap-6">
                    @foreach ($categories as $category)
                        <a href="{{ route('category.show', $category->slug) }}" draggable="false" data-cat
                           class="group block w-[62%] shrink-0 snap-start sm:w-[38%] md:w-[28%] lg:w-[21%] 2xl:w-[16%]">
                            <div class="aspect-[4/5] overflow-hidden bg-beige">
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

    </div>

</x-store-layout>