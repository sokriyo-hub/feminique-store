@use('App\Support\Money')
@props(['product', 'delay' => 0, 'reveal' => true])
@php
    $image = $product->images->first();
    $pricing = $product->cardPricing();
    $soldOut = ! $product->in_stock;
@endphp

<div class="group relative" data-wid="{{ $product->id }}" @if ($reveal) data-reveal style="--d: {{ (int) $delay }}ms" @endif>
    <a href="{{ route('product.show', $product->slug) }}" class="block">
        <div class="tilt relative aspect-[4/5] overflow-hidden rounded-xl bg-beige" data-tilt>
            @if ($image)
                <img src="{{ asset('storage/' . $image->path) }}" alt="{{ $product->name }}" loading="lazy"
                     class="h-full w-full object-cover transition duration-[1400ms] ease-out group-hover:scale-[1.05] {{ $soldOut ? 'opacity-50' : '' }}">
            @else
                <div class="flex h-full w-full items-center justify-center">
                    <span class="font-serif text-xl uppercase tracking-[0.35em] text-cocoa/40">Feminique</span>
                </div>
            @endif

            @if ($pricing['has_discount'] && ! $soldOut)
                <span class="absolute left-3 top-3 z-10 rounded bg-cocoa px-2.5 py-1 text-[11px] tracking-[0.15em] text-ivory">-{{ $pricing['percent'] }}%</span>
            @endif

            @if ($soldOut)
                <span class="absolute inset-x-0 bottom-4 z-10 mx-auto w-fit rounded bg-ivory/90 px-4 py-1.5 text-[11px] uppercase tracking-[0.25em] text-cocoa">{{ __('Sold out') }}</span>
            @endif
        </div>

        <h3 class="mt-5 font-serif text-xl leading-snug text-cocoa {{ $soldOut ? 'opacity-60' : '' }}">{{ $product->name }}</h3>

        <p class="mt-1.5 text-sm {{ $soldOut ? 'opacity-60' : '' }}">
            @if ($pricing['from'])
                <span class="text-cocoa/70">{{ __('From') }}</span>
            @endif
            <span class="text-cocoa">{{ Money::format($pricing['selling']) }}</span>
            @if ($pricing['has_discount'])
                <span class="ml-2 text-cocoa/60 line-through">{{ Money::format($pricing['regular']) }}</span>
            @endif
        </p>
    </a>

    {{-- Wishlist heart --}}
    <button type="button" x-data
            @click="$store.wishlist.toggle({{ $product->id }})"
            :aria-pressed="$store.wishlist.has({{ $product->id }})"
            aria-label="{{ __('Wishlist') }}"
            class="absolute right-3 top-3 z-20 flex h-9 w-9 items-center justify-center rounded-lg border border-cocoa/30 bg-ivory/80 text-cocoa backdrop-blur-sm transition-colors duration-300 hover:bg-cocoa hover:text-ivory">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-colors duration-300" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round"
             :fill="$store.wishlist.has({{ $product->id }}) ? 'currentColor' : 'none'">
            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
        </svg>
    </button>
</div>