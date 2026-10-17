@use('App\Support\Money')
@php
    $imageUrls = $product->images->map(fn ($img) => asset('storage/' . $img->path))->values()->all();
@endphp
<x-store-layout :title="$product->name" :description="\Illuminate\Support\Str::limit(strip_tags((string) $product->description), 150)">
    <article class="mx-auto max-w-7xl px-6 py-12 lg:px-10 lg:py-20">

        <nav class="text-xs uppercase tracking-[0.25em] text-cocoa/70" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="transition hover:text-cocoa">{{ __('Home') }}</a>
            <span class="mx-3">/</span>
            <a href="{{ route('category.show', $product->category->slug) }}" class="transition hover:text-cocoa">{{ $product->category->name }}</a>
        </nav>

        <div class="mt-10 grid gap-12 lg:grid-cols-2 lg:gap-20">

            {{-- Gallery --}}
            <div x-data="{ active: 0, images: @js($imageUrls) }">
                <div class="aspect-[4/5] overflow-hidden bg-beige">
                    @if (count($imageUrls))
                        <img :src="images[active]" src="{{ $imageUrls[0] }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center">
                            <span class="font-serif text-2xl uppercase tracking-[0.35em] text-cocoa/40">Feminique</span>
                        </div>
                    @endif
                </div>

                @if (count($imageUrls) > 1)
                    <div class="mt-4 grid grid-cols-5 gap-3">
                        <template x-for="(url, i) in images" :key="i">
                            <button type="button" @click="active = i"
                                    class="aspect-square overflow-hidden border bg-beige transition"
                                    :class="active === i ? 'border-cocoa' : 'border-transparent opacity-70 hover:opacity-100'"
                                    :aria-label="'Image ' + (i + 1)">
                                <img :src="url" alt="" loading="lazy" class="h-full w-full object-cover">
                            </button>
                        </template>
                    </div>
                @endif
            </div>

            {{-- Details --}}
            <div class="lg:pt-6">
                <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ $product->category->name }}</p>
                <h1 class="mt-4 font-serif text-4xl font-light leading-tight lg:text-5xl">{{ $product->name }}</h1>

                <div class="mt-6 flex flex-wrap items-baseline gap-x-4 gap-y-2">
                    <span class="text-2xl text-cocoa">{{ Money::format($product->selling_price) }}</span>
                    @if ($product->has_discount)
                        <span class="text-lg text-cocoa/60 line-through">{{ Money::format($product->price) }}</span>
                        <span class="bg-cocoa px-2.5 py-1 text-[11px] tracking-[0.15em] text-ivory">-{{ $product->discount_percent }}%</span>
                    @endif
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