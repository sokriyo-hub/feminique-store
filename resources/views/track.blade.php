@use('App\Models\Order')
@use('App\Support\Money')
@php
    $field = 'w-full border-0 border-b border-taupe/60 bg-transparent px-0 py-3 text-cocoa placeholder:text-cocoa/40 focus:border-cocoa focus:outline-none focus:ring-0';
    $label = 'block text-xs uppercase tracking-[0.25em] text-cocoa/70';
    $steps = ['pending', 'confirmed', 'shipped', 'delivered'];
    $statusNames = Order::statusOptions();
    $current = $order ? array_search($order->status, $steps, true) : false;
    $messages = [
        'pending' => __('We have received your order and will confirm it soon.'),
        'confirmed' => __('Your order is confirmed and is being prepared.'),
        'shipped' => __('Your order is on its way to you.'),
        'delivered' => __('Your order has been delivered. Thank you!'),
    ];
@endphp
<x-store-layout :title="__('Track order')" :back="true" :back-url="route('home')">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:py-20">

        <div class="text-center" data-reveal>
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Your order') }}</p>
            <h1 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('Track order') }}</h1>
            <p class="mt-4 text-sm leading-relaxed text-cocoa/80">{{ __('Enter the mobile number and order code you received when you placed your order.') }}</p>
        </div>

        <form method="POST" action="{{ route('track.lookup') }}" class="mt-12 space-y-9" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <div>
                <label for="phone" class="{{ $label }}">{{ __('Mobile number') }}</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ request('phone') }}" required maxlength="20" autocomplete="tel" placeholder="01XXXXXXXXX" class="{{ $field }}">
            </div>

            <div>
                <label for="code" class="{{ $label }}">{{ __('Order code') }}</label>
                <input id="code" name="code" type="text" value="{{ request('code') }}" required maxlength="20" autocomplete="off" autocapitalize="characters" placeholder="FQ-XXXXXXXX" class="{{ $field }} uppercase">
            </div>

            @if ($error)
                <p class="border border-taupe/40 px-5 py-4 text-sm text-mocha" role="alert">{{ $error }}</p>
            @endif

            <button type="submit" :disabled="submitting"
                    class="w-full border border-cocoa bg-cocoa px-8 py-4 text-xs font-medium uppercase tracking-[0.3em] text-ivory transition duration-500 hover:bg-transparent hover:text-cocoa disabled:cursor-default disabled:opacity-60 disabled:hover:bg-cocoa disabled:hover:text-ivory">
                <span x-show="!submitting">{{ __('Track order') }}</span>
                <span x-show="submitting" x-cloak>{{ __('Searching...') }}</span>
            </button>
        </form>

        @if ($order)
            <div class="mt-16 border-t border-taupe/30 pt-12">

                <div class="text-center">
                    <p class="text-xs uppercase tracking-[0.3em] text-cocoa/70">{{ __('Order code') }}</p>
                    <p class="mt-3 font-serif text-3xl tracking-[0.15em] text-cocoa lg:text-4xl">{{ $order->code }}</p>
                    <p class="mt-3 text-sm text-cocoa/70">{{ __('Placed on') }} {{ $order->created_at->format('d M Y, h:i A') }}</p>
                </div>

                {{-- Status --}}
                @if ($order->status === 'cancelled')
                    <div class="mt-10 text-center">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border-2 border-mocha text-mocha">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                        </span>
                        <p class="mt-4 text-sm text-mocha">{{ __('This order was cancelled.') }}</p>
                    </div>
                @else
                    <ol class="mt-12 grid grid-cols-4 text-center">
                        @foreach ($steps as $i => $step)
                            @php
                                $done = $i < $current || ($i === $current && $step === 'delivered');
                                $active = $i === $current && ! $done;
                            @endphp
                            <li class="relative">
                                @if (! $loop->last)
                                    <span class="absolute left-1/2 top-[25px] h-1.5 w-full sm:top-[37px] {{ $i < $current ? 'bg-cocoa' : 'bg-taupe/25' }}"></span>
                                @endif

                                <span class="relative z-10 mx-auto flex h-14 w-14 items-center justify-center rounded-full sm:h-20 sm:w-20
                                    {{ $done ? 'bg-beige text-cocoa' : ($active ? 'bg-beige text-cocoa ring-2 ring-cocoa ring-offset-2 ring-offset-white' : 'border-2 border-taupe/40 bg-white text-cocoa/30') }}">

                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 sm:h-9 sm:w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        @switch($step)
                                            @case('pending')
                                                <path d="M6 8h12l1 12H5L6 8z"/>
                                                <path d="M9 8V7a3 3 0 016 0v1"/>
                                                @break
                                            @case('confirmed')
                                                <path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/>
                                                <path d="M4 7.5l8 4.5 8-4.5M12 12v9"/>
                                                @break
                                            @case('shipped')
                                                <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/>
                                                <circle cx="7" cy="17.5" r="1.8"/>
                                                <circle cx="17" cy="17.5" r="1.8"/>
                                                @break
                                            @default
                                                <path d="M5 13l4 4L19 7"/>
                                        @endswitch
                                    </svg>

                                    @if ($done && $step !== 'delivered')
                                        <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-cocoa text-ivory">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                    @endif
                                </span>

                                <p class="mt-4 text-[11px] uppercase tracking-[0.12em] sm:text-xs sm:tracking-[0.2em] {{ $active ? 'font-medium text-cocoa' : ($done ? 'text-cocoa' : 'text-cocoa/40') }}">{{ __($statusNames[$step]) }}</p>
                            </li>
                        @endforeach
                    </ol>

                    <p class="mt-8 text-center text-sm leading-relaxed text-cocoa/80">{{ $messages[$order->status] }}</p>
                @endif

                {{-- Items --}}
                <ul class="mt-12 divide-y divide-taupe/30 border-y border-taupe/30">
                    @foreach ($order->items as $item)
                        <li class="flex items-baseline justify-between gap-6 py-4">
                            <div class="min-w-0">
                                <p class="font-serif text-lg leading-snug text-cocoa">{{ $item->product_name }}</p>
                                @if ($item->variant_label)
                                    <p class="mt-0.5 text-xs text-cocoa/70">{{ $item->variant_label }}</p>
                                @endif
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

                <p class="mt-6 text-center text-xs uppercase tracking-[0.25em] text-cocoa/70">{{ __('Cash on Delivery') }}</p>
            </div>
        @endif
    </section>
</x-store-layout>