@use('App\Support\Money')
@php
    $imageUrls = $product->images->map(fn ($img) => asset('storage/' . $img->path))->values()->all();
    $imageMeta = $product->images->map(fn ($img) => ['name' => $img->option_name, 'value' => $img->option_value])->values()->all();
    $pricing = $product->cardPricing();

    $optionsData = $product->has_variants
        ? $product->options->map(fn ($o) => ['name' => $o->name, 'choices' => array_values((array) $o->choices)])->values()->all()
        : [];

    $variantsData = $product->has_variants
        ? $product->variants->map(function ($v) use ($product) {
            [$regular, $selling] = $product->variantPrices($v);

            return [
                'id' => $v->id,
                'combo' => (array) $v->combination,
                'stock' => (int) $v->stock,
                'regular' => $regular,
                'selling' => $selling,
            ];
        })->values()->all()
        : [];

    $chooseMsg = __('Please choose :options.', ['options' => $product->options->pluck('name')->implode(', ')]);

    $arrowClass = 'absolute top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center border border-cocoa/30 bg-ivory/80 text-cocoa backdrop-blur-sm transition-colors duration-300 hover:bg-cocoa hover:text-ivory';
    $stepBtn = 'flex h-11 w-11 items-center justify-center text-cocoa transition-colors hover:bg-cocoa hover:text-ivory disabled:cursor-default disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-cocoa';
    $actionBtn = 'px-8 py-4 text-xs font-medium uppercase tracking-[0.25em] transition duration-500 disabled:cursor-default disabled:opacity-40';
@endphp
<x-store-layout :title="$product->name"
                :description="\Illuminate\Support\Str::limit(strip_tags((string) $product->description), 150)"
                :back="true"
                :back-url="route('category.show', $product->category->slug)">
    <article class="wrap pb-10 pt-3 lg:pb-14 lg:pt-5">

        <nav class="text-xs uppercase tracking-[0.25em] text-cocoa/70" aria-label="Breadcrumb" data-reveal>
            <a href="{{ route('home') }}" class="transition-colors hover:text-cocoa">{{ __('Home') }}</a>
            <span class="mx-3">/</span>
            <a href="{{ route('category.show', $product->category->slug) }}" class="transition-colors hover:text-cocoa">{{ $product->category->name }}</a>
        </nav>

        {{-- One shared state: selected choices, chosen variant, sold out, quantity limit --}}
        <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(20rem,calc(62vh*0.8))_minmax(0,1fr)] lg:gap-16 xl:gap-24"
             x-data="{
                options: @js($optionsData),
                variants: @js($variantsData),
                images: @js($imageMeta),
                hasVariants: @js((bool) $product->has_variants),
                baseStock: @js((int) $product->stock),
                chooseMsg: @js($chooseMsg),
                sel: {},
                q: 1,
                warn: false,
                get variant() {
                    if (!this.hasVariants) return null;
                    if (!this.options.every(o => this.sel[o.name])) return null;
                    return this.variants.find(v => this.options.every(o => v.combo[o.name] === this.sel[o.name])) || null;
                },
                get soldOut() {
                    if (!this.hasVariants) return this.baseStock <= 0;
                    if (this.variant) return this.variant.stock <= 0;
                    return this.variants.every(v => v.stock <= 0);
                },
                get maxQty() {
                    if (!this.hasVariants) return Math.max(1, this.baseStock);
                    if (this.variant) return Math.max(1, this.variant.stock);
                    return Math.max(1, ...this.variants.map(v => v.stock));
                },
                get boundImage() {
                    const i = this.images.findIndex(im => im.name && this.sel[im.name] === im.value);
                    return i < 0 ? null : i;
                },
                available(name, choice) {
                    return this.variants.some(v => v.combo[name] === choice && v.stock > 0
                        && this.options.every(o => o.name === name || !this.sel[o.name] || v.combo[o.name] === this.sel[o.name]));
                },
                pick(name, choice) {
                    this.sel = { ...this.sel, [name]: choice };
                    this.warn = false;
                    this.q = Math.min(this.q, this.maxQty);
                },
                fmt(n) { return 'Tk ' + Number(n).toFixed(2).replace(/\.00$/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',') }
             }">

            {{-- Gallery --}}
            <div class="mx-auto w-full max-w-[max(20rem,calc(62svh*0.8))] lg:mx-0 lg:max-w-none"
                 x-data="productGallery({{ count($imageUrls) }})"
                 x-init="$watch('boundImage', (i) => { if (i !== null) go(i) })"
                 @keydown.window.arrow-left="prev()"
                 @keydown.window.arrow-right="next()"
                 data-reveal>

                <div class="relative aspect-[4/5] select-none overflow-hidden rounded-xl bg-beige"
                     :class="zoom ? 'cursor-zoom-in' : ''"
                     @mousemove="onMove($event)"
                     @mouseleave="zoom = false"
                     @touchstart.passive="touchStart($event)"
                     @touchend.passive="touchEnd($event)">

                    @forelse ($imageUrls as $url)
                        <img src="{{ $url }}" alt="{{ $product->name }}" draggable="false"
                             @if (! $loop->first) loading="lazy" @endif
                             class="absolute inset-0 h-full w-full object-cover"
                             :class="active === {{ $loop->index }} ? (soldOut ? 'opacity-50' : 'opacity-100') : 'pointer-events-none opacity-0'"
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

                    {{-- Sold out badge on the image --}}
                    <span x-show="soldOut" x-cloak
                          class="pointer-events-none absolute inset-x-0 bottom-4 z-10 mx-auto w-fit rounded bg-ivory/90 px-4 py-1.5 text-[11px] uppercase tracking-[0.25em] text-cocoa">{{ __('Sold out') }}</span>

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
                                    class="aspect-square overflow-hidden rounded-lg border bg-beige transition"
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

                {{-- Price before a variant is chosen --}}
                <div class="mt-6 flex flex-wrap items-baseline gap-x-4 gap-y-2" x-show="!variant">
                    @if ($pricing['from'])
                        <span class="text-sm text-cocoa/70">{{ __('From') }}</span>
                    @endif
                    <span class="text-2xl text-cocoa">{{ Money::format($pricing['selling']) }}</span>
                    @if ($pricing['has_discount'])
                        <span class="text-lg text-cocoa/60 line-through">{{ Money::format($pricing['regular']) }}</span>
                        <span class="bg-cocoa px-2.5 py-1 text-[11px] tracking-[0.15em] text-ivory">-{{ $pricing['percent'] }}%</span>
                    @endif
                </div>

                {{-- Price of the chosen variant --}}
                <div class="mt-6 flex flex-wrap items-baseline gap-x-4 gap-y-2" x-show="variant" x-cloak>
                    <span class="text-2xl text-cocoa" x-text="variant ? fmt(variant.selling) : ''"></span>
                    <span class="text-lg text-cocoa/60 line-through" x-show="variant && variant.selling < variant.regular" x-text="variant ? fmt(variant.regular) : ''"></span>
                    <span class="bg-cocoa px-2.5 py-1 text-[11px] tracking-[0.15em] text-ivory" x-show="variant && variant.selling < variant.regular"
                          x-text="variant ? '-' + Math.round((variant.regular - variant.selling) / variant.regular * 100) + '%' : ''"></span>
                </div>

                <form method="POST" action="{{ route('cart.add') }}" class="mt-8"
                      @submit="if (soldOut || (hasVariants && !variant)) { $event.preventDefault(); if (hasVariants && !variant && !soldOut) warn = true }">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="variant_id" :value="variant ? variant.id : ''">
                    <input type="hidden" name="quantity" :value="q">

                    {{-- Variant buttons: sold out choices are dimmed --}}
                    @if ($product->has_variants)
                        <div class="space-y-7">
                            @foreach ($product->options as $option)
                                <div>
                                    <p class="text-xs uppercase tracking-[0.25em] text-cocoa/70">
                                        {{ $option->name }}<span x-show="sel[@js($option->name)]" x-cloak x-text="': ' + sel[@js($option->name)]"></span>
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2.5">
                                        @foreach ((array) $option->choices as $choice)
                                            <button type="button"
                                                    @click="pick(@js($option->name), @js($choice))"
                                                    :aria-pressed="sel[@js($option->name)] === @js($choice)"
                                                    :class="[
                                                        sel[@js($option->name)] === @js($choice) ? 'border-cocoa bg-cocoa text-ivory' : 'border-cocoa/40 text-cocoa hover:border-cocoa',
                                                        available(@js($option->name), @js($choice)) ? '' : 'opacity-40 line-through'
                                                    ]"
                                                    class="min-w-[3rem] border px-4 py-2.5 text-sm transition-colors duration-300">{{ $choice }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p x-show="warn && !variant" x-cloak class="mt-4 text-sm text-mocha" role="alert" x-text="chooseMsg"></p>
                    @endif

                    <div class="{{ $product->has_variants ? 'mt-8' : '' }} flex items-center gap-5">
                        <span class="text-xs uppercase tracking-[0.25em] text-cocoa/70">{{ __('Quantity') }}</span>
                        <div class="inline-flex items-center border border-cocoa/40">
                            <button type="button" aria-label="{{ __('Decrease quantity') }}" @click="if (q > 1) q--" :disabled="q <= 1 || soldOut" class="{{ $stepBtn }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"><path d="M5 12h14"/></svg>
                            </button>
                            <span x-text="q" class="w-12 text-center text-sm text-cocoa"></span>
                            <button type="button" aria-label="{{ __('Increase quantity') }}" @click="if (q < maxQty) q++" :disabled="q >= maxQty || soldOut" class="{{ $stepBtn }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button type="submit" name="buy_now" value="1" :disabled="soldOut"
                                class="{{ $actionBtn }} border border-cocoa bg-cocoa text-ivory hover:bg-transparent hover:text-cocoa disabled:hover:bg-cocoa disabled:hover:text-ivory">{{ __('Buy now') }}</button>

                        <button type="submit" :disabled="soldOut"
                                class="{{ $actionBtn }} border border-cocoa/40 bg-transparent text-cocoa hover:bg-cocoa hover:text-ivory disabled:hover:bg-transparent disabled:hover:text-cocoa">{{ __('Add to cart') }}</button>

                        <button type="button"
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
                </form>

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