@use('App\Support\Money')
@php
    $imageUrls = $product->images->map(fn ($img) => asset('storage/' . $img->path))->values()->all();
    $arrowClass = 'absolute top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center border border-cocoa/30 bg-ivory/80 text-cocoa backdrop-blur-sm transition-colors duration-300 hover:bg-cocoa hover:text-ivory';
@endphp
<x-store-layout :title="$product->name"
                :description="\Illuminate\Support\Str::limit(strip_tags((string) $product->description), 150)"
                :back="true"
                :back-url="route('category.show', $product->category->slug)">
    <article class="wrap py-10 lg:py-14">

        <nav class="text-xs uppercase tracking-[0.25em] text-cocoa/70" aria-label="Breadcrumb" data-reveal>
            <a href="{{ route('home') }}" class="transition-colors hover:text-cocoa">{{ __('Home') }}</a>
            <span class="mx-3">/</span>
            <a href="{{ route('category.show', $product->category->slug) }}" class="transition-colors hover:text-cocoa">{{ $product->category->name }}</a>
        </nav>

        <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(20rem,calc(62vh*0.8))_minmax(0,1fr)] lg:gap-16 xl:gap-24">

            {{-- Gallery --}}
            <div class="mx-auto w-full max-w-[calc(62vh*0.8)] lg:mx-0 lg:max-w-none"
                 x-data="productGallery({{ count($imageUrls) }})"
                 @keydown.window.arrow-left="prev()"
                 @keydown.window.arrow-right="next()"
                 data-reveal>

                <div class="relative aspect-[4/5] select-none overflow-hidden bg-beige"
                     :class="zoom ? 'cursor-zoom-in' : ''"
                     @mousemove="onMove($event)"
                     @mouseleave="zoom = false"
                     @touchstart.passive="touchStart($event)"
                     @touchend.passive="touchEnd($event)">

                    @forelse ($imageUrls as $url)
                        <img src="{{ $url }}" alt="{{ $product->name }}" draggable="false"
                             @if (! $loop->first) loading="lazy" @endif
                             class="absolute inset-0 h-full w-full object-cover"
                             :class="active === {{ $loop->index }} ? 'opacity-100' : 'pointer-events-none opacity-0'"
                             :style="{
                                 transition: 'transform 350ms ease-out, opacity 600ms ease',
                                 transform: (active === {{ $loop->index }} && zoom) ? 'scale(2.4)' : 'scale(1)',
                                 transformOrigin: ox + '% ' + oy + '%'
                             }">
                    @empty
                        <div class="flex h-full w-full items-center justify-center">
                            <span class="font-serif text-2xl uppercase tracking-[0.35em] text-cocoa/40">Feminique</span>
                        </div>
                    @endforelse

                    @if (count($imageUrls) > 1)
                        <button type="button" data-nozoom @click="prev()" aria-label="{{ __('Previous') }}" class="{{ $arrowClass }} left-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
                        </button>
                        <button type="button" data-nozoom @click="next()" aria-label="{{ __('Next') }}" class="{{ $arrowClass }} right-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
                        </button>
                    @endif
                </div>

                @if (count($imageUrls) > 1)
                    <div class="mt-3 grid grid-cols-5 gap-2.5 sm:grid-cols-6">
                        @foreach ($imageUrls as $url)
                            <button type="button" @click="go({{ $loop->index }})"
                                    class="aspect-square overflow-hidden border bg-beige transition"
                                    :class="active === {{ $loop->index }} ? 'border-cocoa' : 'border-transparent opacity-70 hover:opacity-100'"
                                    aria-label="Image {{ $loop->iteration }}">
                                <img src="{{ $url }}" alt="" loading="lazy" draggable="false" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Details --}}
            <div class="max-w-2xl lg:pt-6" data-reveal style="--d: 120ms">
                <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ $product->category->name }}</p>
                <h1 class="mt-4 font-serif text-4xl font-light leading-tight lg:text-5xl">{{ $product->name }}</h1>

                <div class="mt-6 flex flex-wrap items-baseline gap-x-4 gap-y-2">
                    <span class="text-2xl text-cocoa">{{ Money::format($product->selling_price) }}</span>
                    @if ($product->has_discount)
                        <span class="text-lg text-cocoa/60 line-through">{{ Money::format($product->price) }}</span>
                        <span class="bg-cocoa px-2.5 py-1 text-[11px] tracking-[0.15em] text-ivory">-{{ $product->discount_percent }}%</span>
                    @endif
                </div>

                {{-- Actions (Add to cart and Buy now will be added to this row in S7) --}}
                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="button" x-data
                            @click="$store.wishlist.toggle({{ $product->id }})"
                            :aria-pressed="$store.wishlist.has({{ $product->id }})"
                            class="inline-flex items-center gap-3 border border-cocoa/40 px-8 py-4 text-xs font-medium uppercase tracking-[0.25em] text-cocoa transition duration-500 hover:bg-cocoa hover:text-ivory">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round"
                             :fill="$store.wishlist.has({{ $product->id }}) ? 'currentColor' : 'none'">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        </svg>
                        <span x-show="!$store.wishlist.has({{ $product->id }})">{{ __('Add to wishlist') }}</span>
                        <span x-show="$store.wishlist.has({{ $product->id }})" x-cloak>{{ __('Saved to wishlist') }}</span>
                    </button>
                </div>

                @if ($product->description)
                    <div class="mt-10 border-t border-taupe/30 pt-10">
                        <p class="whitespace-pre-line leading-8 text-cocoa/90">{{ $product->description }}</p>
                    </div>
                @endif

                <p class="mt-10 border-t border-taupe/30 pt-6 text-xs uppercase tracking-[0.25em] text-cocoa/70">{{ __('Cash on Delivery available') }}</p>
            </div>
        </div>
    </article>
</x-store-layout>