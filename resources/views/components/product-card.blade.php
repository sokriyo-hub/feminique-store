@use('App\Support\Money')
@props(['product', 'delay' => 0])
@php($image = $product->images->first())

<a href="{{ route('product.show', $product->slug) }}" class="group block" data-reveal style="--d: {{ (int) $delay }}ms">
    <div class="tilt relative aspect-[4/5] overflow-hidden bg-beige" data-tilt>
        @if ($image)
            <img src="{{ asset('storage/' . $image->path) }}" alt="{{ $product->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-[1400ms] ease-out group-hover:scale-[1.05]">
        @else
            <div class="flex h-full w-full items-center justify-center">
                <span class="font-serif text-xl uppercase tracking-[0.35em] text-cocoa/40">Feminique</span>
            </div>
        @endif

        @if ($product->has_discount)
            <span class="absolute left-3 top-3 z-10 bg-cocoa px-2.5 py-1 text-[11px] tracking-[0.15em] text-ivory">-{{ $product->discount_percent }}%</span>
        @endif
    </div>

    <h3 class="mt-5 font-serif text-xl leading-snug text-cocoa">{{ $product->name }}</h3>

    <p class="mt-1.5 text-sm">
        <span class="text-cocoa">{{ Money::format($product->selling_price) }}</span>
        @if ($product->has_discount)
            <span class="ml-2 text-cocoa/60 line-through">{{ Money::format($product->price) }}</span>
        @endif
    </p>
</a>