@use('App\Support\Money')
<x-store-layout :title="__('Order placed')">
    <section class="mx-auto max-w-3xl px-6 pb-14 pt-6 lg:pb-20 lg:pt-10" x-data="receipt('{{ $order->code }}')">

        <div class="no-print text-center" data-reveal>
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Thank you') }}</p>
            <h1 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('Your order is placed') }}</h1>
            <p class="mt-4 text-sm leading-relaxed text-cocoa/80">{{ __('We have received your order and will confirm it soon.') }}</p>
        </div>

        {{-- Receipt: this exact box becomes the image / PDF --}}
        <div x-ref="card" class="mt-10 bg-ivory px-6 pb-12 pt-8 sm:px-10">

            <div class="flex items-baseline justify-between gap-4 border-b border-taupe/30 pb-5">
                <p class="font-serif text-2xl font-medium uppercase tracking-[0.3em] text-cocoa">Feminique</p>
                <p class="text-xs uppercase tracking-[0.25em] text-cocoa/70">{{ __('Order receipt') }}</p>
            </div>

            <div class="mt-8 border border-taupe/50 px-6 py-8 text-center">
                <p class="text-xs uppercase tracking-[0.3em] text-cocoa/70">{{ __('Order code') }}</p>
                <p class="mt-3 select-all font-serif text-4xl tracking-[0.15em] text-cocoa lg:text-5xl">{{ $order->code }}</p>
                <p class="mt-4 text-xs text-cocoa/70">{{ $order->created_at->format('d M Y, h:i A') }}</p>
            </div>

            <div class="mt-10 grid gap-8 sm:grid-cols-2">
                <div>
                    <h2 class="text-xs uppercase tracking-[0.3em] text-cocoa/70">{{ __('Delivery to') }}</h2>
                    <div class="mt-4 space-y-1 text-sm leading-relaxed text-cocoa">
                        <p>{{ $order->customer_name }}</p>
                        <p>{{ $order->phone }}</p>
                        <p class="whitespace-pre-line">{{ $order->address }}</p>
                    </div>
                </div>
                <div>
                    <h2 class="text-xs uppercase tracking-[0.3em] text-cocoa/70">{{ __('Payment') }}</h2>
                    <p class="mt-4 text-sm leading-relaxed text-cocoa">{{ __('Cash on Delivery') }}</p>
                    @if ($order->delivery_zone_name)
                        <p class="mt-1 text-sm text-cocoa/70">{{ $order->delivery_zone_name }}</p>
                    @endif
                </div>
            </div>

            <ul class="mt-10 divide-y divide-taupe/30 border-y border-taupe/30">
                @foreach ($order->items as $item)
                    <li class="flex items-baseline justify-between gap-6 py-4">
                        <div class="min-w-0">
                            <p class="font-serif text-lg leading-snug text-cocoa">{{ $item->product_name }}</p>
                            <p class="mt-0.5 text-xs text-cocoa/70">{{ $item->quantity }} &times; {{ Money::format($item->unit_price) }}</p>
                        </div>
                        <p class="shrink-0 text-sm text-cocoa">{{ Money::format($item->line_total) }}</p>
                    </li>
                @endforeach
            </ul>

            <dl class="mt-6 space-y-3 text-sm">
                <div class="flex items-baseline justify-between">
                    <dt class="text-cocoa/80">{{ __('Subtotal') }}</dt>
                    <dd class="text-cocoa">{{ Money::format($order->subtotal) }}</dd>
                </div>
                <div class="flex items-baseline justify-between">
                    <dt class="text-cocoa/80">{{ __('Delivery charge') }}</dt>
                    <dd class="text-cocoa">{{ (float) $order->delivery_charge > 0 ? Money::format($order->delivery_charge) : __('Free') }}</dd>
                </div>
                <div class="flex items-baseline justify-between border-t border-taupe/30 pt-4">
                    <dt class="text-xs uppercase tracking-[0.25em] text-cocoa/70">{{ __('Total') }}</dt>
                    <dd class="text-xl text-cocoa">{{ Money::format($order->total) }}</dd>
                </div>
            </dl>

            <div class="mt-8 border-t border-taupe/30 pb-2 pt-5 text-center text-xs text-cocoa/70">
                <p class="leading-6">{{ __('Track your order with your mobile number and order code at') }}</p>
                <p class="mt-1 break-all leading-6 text-cocoa">{{ route('track') }}</p>
            </div>
        </div>

        {{-- Buttons (not part of the image / PDF) --}}
        <div class="no-print mt-8 flex flex-wrap justify-center gap-3">
            <button type="button" @click="download()" :disabled="busy"
                    class="border border-cocoa bg-cocoa px-8 py-4 text-xs font-medium uppercase tracking-[0.25em] text-ivory transition duration-500 hover:bg-transparent hover:text-cocoa disabled:cursor-default disabled:opacity-60 disabled:hover:bg-cocoa disabled:hover:text-ivory">
                <span x-show="!busy">{{ __('Download image') }}</span>
                <span x-show="busy" x-cloak>{{ __('Preparing...') }}</span>
            </button>
            <button type="button" @click="print()"
                    class="border border-cocoa/40 bg-transparent px-8 py-4 text-xs font-medium uppercase tracking-[0.25em] text-cocoa transition duration-500 hover:bg-cocoa hover:text-ivory">{{ __('Save as PDF') }}</button>
        </div>

        <p x-show="failed" x-cloak class="no-print mt-4 text-center text-xs text-mocha" role="alert">{{ __('Could not create the image. Please use "Save as PDF" or take a screenshot.') }}</p>

        <div class="no-print mt-10 text-center">
            <a href="{{ route('track') }}" class="nav-link mr-8 inline-block text-xs uppercase tracking-[0.25em] text-cocoa/80 transition-colors hover:text-cocoa">{{ __('Track your order') }}</a>
            <a href="{{ route('home') }}" class="nav-link inline-block text-xs uppercase tracking-[0.25em] text-cocoa/80 transition-colors hover:text-cocoa">{{ __('Continue shopping') }}</a>
        </div>
    </section>
</x-store-layout>