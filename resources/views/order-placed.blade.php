@use('App\Support\Money')
<x-store-layout :title="__('Order placed')">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:py-20">

        <div class="text-center" data-reveal>
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Thank you') }}</p>
            <h1 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('Your order is placed') }}</h1>
            <p class="mt-4 text-sm leading-relaxed text-cocoa/80">{{ __('We have received your order and will confirm it soon.') }}</p>
        </div>

        <div class="mt-12 border border-taupe/50 px-6 py-8 text-center" data-reveal style="--d: 100ms">
            <p class="text-xs uppercase tracking-[0.3em] text-cocoa/70">{{ __('Order code') }}</p>
            <p class="mt-3 select-all font-serif text-4xl tracking-[0.15em] text-cocoa lg:text-5xl">{{ $order->code }}</p>
            <a href="{{ route('track') }}" class="nav-link mt-5 inline-block text-xs uppercase tracking-[0.25em] text-cocoa/80 transition-colors hover:text-cocoa">{{ __('Track your order') }}</a>
        </div>

        <div class="mt-12 grid gap-10 sm:grid-cols-2" data-reveal style="--d: 150ms">
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

        <ul class="mt-12 divide-y divide-taupe/30 border-y border-taupe/30" data-reveal style="--d: 200ms">
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

        <div class="mt-14 text-center">
            <a href="{{ route('home') }}" class="inline-block border border-cocoa bg-transparent px-10 py-4 text-xs font-medium uppercase tracking-[0.3em] text-cocoa transition duration-500 hover:bg-cocoa hover:text-ivory">{{ __('Continue shopping') }}</a>
        </div>
    </section>
</x-store-layout>