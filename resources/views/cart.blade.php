@use('App\Support\Money')
<x-store-layout :title="__('Cart')" :back="true" :back-url="route('home')">
        <section class="wrap pb-10 pt-3 lg:pb-14 lg:pt-5">

        <div class="text-center" data-reveal>
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Your selection') }}</p>
            <h1 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('Cart') }}</h1>
        </div>

        {{-- Red messages: stock changed, quantity was adjusted --}}
        @if (! empty($warnings))
            <div class="mx-auto mt-10 max-w-3xl space-y-2" role="alert">
                @foreach ($warnings as $warning)
                    <p class="border border-red-300 bg-red-50 px-5 py-3 text-sm text-red-700">{{ $warning }}</p>
                @endforeach
            </div>
        @endif

        @if ($lines->isEmpty())
            <div class="mt-16 text-center">
                <p class="font-serif text-2xl text-cocoa">{{ __('Your cart is empty.') }}</p>
                <p class="mt-3 text-sm text-cocoa/70">{{ __('Pieces you add will appear here.') }}</p>
                <a href="{{ route('home') }}#collections" class="mt-8 inline-block border border-cocoa bg-transparent px-10 py-4 text-xs font-medium uppercase tracking-[0.3em] text-cocoa transition duration-500 hover:bg-cocoa hover:text-ivory">{{ __('Continue shopping') }}</a>
            </div>
        @else
            <div class="mt-14 grid gap-12 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-16 xl:grid-cols-[minmax(0,1fr)_26rem] xl:gap-24">

                {{-- Items --}}
                <ul class="divide-y divide-taupe/30 border-y border-taupe/30">
                    @foreach ($lines as $line)
                        @php($product = $line->product)
                        <li class="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-5 py-6 sm:grid-cols-[7rem_minmax(0,1fr)] sm:gap-8">
                            <a href="{{ route('product.show', $product->slug) }}" class="block aspect-[4/5] overflow-hidden rounded-lg bg-beige">
                                @if ($line->image)
                                    <img src="{{ asset('storage/' . $line->image->path) }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <span class="flex h-full w-full items-center justify-center font-serif text-xs uppercase tracking-[0.3em] text-cocoa/40">Feminique</span>
                                @endif
                            </a>

                            <div class="flex min-w-0 flex-col justify-between gap-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <a href="{{ route('product.show', $product->slug) }}" class="font-serif text-xl leading-snug text-cocoa transition-colors hover:text-mocha">{{ $product->name }}</a>
                                        @if ($line->label)
                                            <p class="mt-1 text-xs text-cocoa/70">{{ $line->label }}</p>
                                        @endif
                                        <p class="mt-1.5 text-sm">
                                            <span class="text-cocoa">{{ Money::format($line->unit_price) }}</span>
                                            @if ($line->regular_price > $line->unit_price)
                                                <span class="ml-2 text-cocoa/60 line-through">{{ Money::format($line->regular_price) }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-cocoa">{{ Money::format($line->line_total) }}</p>
                                </div>

                                <div class="flex items-center justify-between gap-4">
                                    {{-- Quantity: limit = stock. Changing it saves automatically --}}
                                    <form method="POST" action="{{ route('cart.update', ['line' => $line->key]) }}" x-data="{ q: {{ (int) $line->quantity }}, max: {{ max(1, (int) $line->stock) }} }">
                                        @csrf
                                        <input type="hidden" name="quantity" :value="q">
                                        <div class="inline-flex items-center border border-cocoa/40">
                                            <button type="button" aria-label="{{ __('Decrease quantity') }}"
                                                    @click="if (q > 1) { q--; $nextTick(() => $el.closest('form').requestSubmit()) }"
                                                    :disabled="q <= 1"
                                                    class="flex h-10 w-10 items-center justify-center text-cocoa transition-colors hover:bg-cocoa hover:text-ivory disabled:cursor-default disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-cocoa">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"><path d="M5 12h14"/></svg>
                                            </button>
                                            <span x-text="q" class="w-10 text-center text-sm text-cocoa"></span>
                                            <button type="button" aria-label="{{ __('Increase quantity') }}"
                                                    @click="if (q < max) { q++; $nextTick(() => $el.closest('form').requestSubmit()) }"
                                                    :disabled="q >= max"
                                                    class="flex h-10 w-10 items-center justify-center text-cocoa transition-colors hover:bg-cocoa hover:text-ivory disabled:cursor-default disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-cocoa">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                            </button>
                                        </div>
                                    </form>

                                    <form method="POST" action="{{ route('cart.remove', ['line' => $line->key]) }}">
                                        @csrf
                                        <button type="submit" class="nav-link text-xs uppercase tracking-[0.25em] text-cocoa/70 transition-colors hover:text-cocoa">{{ __('Remove') }}</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Summary --}}
                <aside class="h-fit border border-taupe/30 p-8 lg:sticky lg:top-32">
                    <h2 class="text-xs uppercase tracking-[0.3em] text-cocoa/70">{{ __('Order summary') }}</h2>

                    <div class="mt-6 flex items-baseline justify-between border-b border-taupe/30 pb-6">
                        <span class="text-sm text-cocoa/80">{{ __('Subtotal') }}</span>
                        <span class="text-xl text-cocoa">{{ Money::format($subtotal) }}</span>
                    </div>

                    <p class="mt-6 text-sm leading-relaxed text-cocoa/70">{{ __('Delivery charge is added at checkout.') }}</p>
                    <p class="mt-3 text-xs uppercase tracking-[0.25em] text-cocoa/70">{{ __('Cash on Delivery available') }}</p>

                    <a href="{{ route('checkout') }}"
                       class="mt-8 block border border-cocoa bg-cocoa px-8 py-4 text-center text-xs font-medium uppercase tracking-[0.3em] text-ivory transition duration-500 hover:bg-transparent hover:text-cocoa">{{ __('Checkout') }}</a>

                    <a href="{{ route('home') }}#collections" class="nav-link mt-6 inline-block text-xs uppercase tracking-[0.25em] text-cocoa/80 transition-colors hover:text-cocoa">{{ __('Continue shopping') }}</a>
                </aside>
            </div>
        @endif
    </section>

    <x-ad-slot name="cart_bottom" />
</x-store-layout>