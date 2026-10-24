@use('App\Support\Money')
@php
    $field = 'w-full border-0 border-b border-taupe/60 bg-transparent px-0 py-3 text-cocoa placeholder:text-cocoa/40 focus:border-cocoa focus:outline-none focus:ring-0';
    $label = 'block text-xs uppercase tracking-[0.25em] text-cocoa/70';
@endphp
<x-store-layout :title="__('Checkout')" :back="true" :back-url="route('cart')">

    <x-ad-slot name="checkout_above" />

    <section class="wrap py-10 lg:py-14">

        <div class="text-center" data-reveal>
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Almost there') }}</p>
            <h1 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('Checkout') }}</h1>
        </div>

        @if ($zones->isEmpty())
            <p class="mt-16 text-center text-cocoa/80">{{ __('Delivery is not available right now. Please contact us.') }}</p>
        @else
            <form method="POST" action="{{ route('checkout.store') }}"
                  class="mt-14 grid gap-12 lg:grid-cols-[minmax(0,1fr)_24rem] lg:gap-16 xl:grid-cols-[minmax(0,1fr)_28rem] xl:gap-24"
                  x-data="{
                      submitting: false,
                      zone: {{ (int) old('zone_id', $zones->first()->id) }},
                      zones: @js($zoneData),
                      subtotal: {{ $subtotal }},
                      get charge() { const z = this.zones.find(z => z.id === Number(this.zone)); return z ? z.charge : 0 },
                      get total() { return this.subtotal + this.charge },
                      fmt(n) { return 'Tk ' + Number(n).toFixed(2).replace(/\.00$/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',') }
                  }"
                  @submit="submitting = true">
                @csrf

                {{-- The total the customer sees. If the price changes before the order is placed, the server refuses it. --}}
                <input type="hidden" name="seen_total" :value="total">

                {{-- Bot-দের ফাঁদ: মানুষের চোখে পড়ে না --}}
                <div class="hidden" aria-hidden="true">
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                {{-- Details --}}
                <div class="space-y-9">
                    <h2 class="text-xs uppercase tracking-[0.3em] text-cocoa/70">{{ __('Your details') }}</h2>

                    <div>
                        <label for="name" class="{{ $label }}">{{ __('Full name') }}</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required minlength="2" maxlength="100" autocomplete="name" class="{{ $field }}">
                        @error('name') <p class="mt-2 text-xs text-mocha">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="phone" class="{{ $label }}">{{ __('Mobile number') }}</label>
                        <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" required maxlength="20" autocomplete="tel" placeholder="01XXXXXXXXX" class="{{ $field }}">
                        @error('phone') <p class="mt-2 text-xs text-mocha">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="address" class="{{ $label }}">{{ __('Delivery address') }}</label>
                        <textarea id="address" name="address" rows="3" required minlength="5" maxlength="500" autocomplete="street-address" class="{{ $field }} resize-none">{{ old('address') }}</textarea>
                        @error('address') <p class="mt-2 text-xs text-mocha">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="email" class="{{ $label }}">{{ __('Email (optional)') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" class="{{ $field }}">
                        @error('email') <p class="mt-2 text-xs text-mocha">{{ $message }}</p> @enderror
                    </div>

                    {{-- Delivery zone: একটা zone থাকলে বাক্স দেখানো হয় না --}}
                    @if ($zones->count() > 1)
                        <div>
                            <p class="{{ $label }}">{{ __('Delivery area') }}</p>
                            <div class="mt-4 space-y-3">
                                @foreach ($zones as $zone)
                                    <label class="flex cursor-pointer items-center justify-between gap-4 border border-taupe/40 px-5 py-4 transition-colors has-[:checked]:border-cocoa has-[:checked]:bg-beige/40">
                                        <span class="flex items-center gap-4">
                                            <input type="radio" name="zone_id" value="{{ $zone->id }}" x-model="zone" class="h-4 w-4 border-cocoa text-cocoa focus:ring-0">
                                            <span class="text-sm text-cocoa">{{ $zone->name }}</span>
                                        </span>
                                        <span class="text-sm text-cocoa/80" x-text="(zones.find(z => z.id === {{ $zone->id }}) || {charge: 0}).charge === 0 ? @js(__('Free')) : fmt(zones.find(z => z.id === {{ $zone->id }}).charge)"></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('zone_id') <p class="mt-2 text-xs text-mocha">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="border border-taupe/30 px-5 py-4 text-sm text-cocoa/80">
                        {{ __('Payment: Cash on Delivery. You pay in cash when your order arrives.') }}
                    </div>
                </div>

                {{-- Summary --}}
                <aside class="h-fit border border-taupe/30 p-8 lg:sticky lg:top-32">
                    <h2 class="text-xs uppercase tracking-[0.3em] text-cocoa/70">{{ __('Order summary') }}</h2>

                    <ul class="mt-6 divide-y divide-taupe/30 border-y border-taupe/30">
                        @foreach ($lines as $line)
                            <li class="grid grid-cols-[3.5rem_minmax(0,1fr)_auto] items-center gap-4 py-4">
                                <div class="aspect-[4/5] overflow-hidden rounded-md bg-beige">
                                    @if ($line->image)
                                        <img src="{{ asset('storage/' . $line->image->path) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-serif text-lg leading-snug text-cocoa">{{ $line->product->name }}</p>
                                    @if ($line->label)
                                        <p class="truncate text-xs text-cocoa/70">{{ $line->label }}</p>
                                    @endif
                                    <p class="mt-0.5 text-xs text-cocoa/70">{{ $line->quantity }} &times; {{ Money::format($line->unit_price) }}</p>
                                </div>
                                <p class="text-sm text-cocoa">{{ Money::format($line->line_total) }}</p>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex items-baseline justify-between">
                            <dt class="text-cocoa/80">{{ __('Subtotal') }}</dt>
                            <dd class="text-cocoa">{{ Money::format($subtotal) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <dt class="text-cocoa/80">{{ __('Delivery charge') }}</dt>
                            <dd class="text-cocoa" x-text="charge === 0 ? @js(__('Free')) : fmt(charge)"></dd>
                        </div>
                        <div class="flex items-baseline justify-between border-t border-taupe/30 pt-4">
                            <dt class="text-xs uppercase tracking-[0.25em] text-cocoa/70">{{ __('Total') }}</dt>
                            <dd class="text-xl text-cocoa" x-text="fmt(total)"></dd>
                        </div>
                    </dl>

                    @if ($freeCampaign)
                        <p class="mt-5 text-xs leading-relaxed text-cocoa/70">{{ __('Free delivery during :name.', ['name' => $freeCampaign]) }}</p>
                    @elseif ($freeFrom !== null)
                        <p class="mt-5 text-xs leading-relaxed text-cocoa/70">{{ __('Free delivery on orders of :amount or more.', ['amount' => Money::format($freeFrom)]) }}</p>
                    @endif

                    <button type="submit" :disabled="submitting"
                            class="mt-8 w-full border border-cocoa bg-cocoa px-8 py-4 text-xs font-medium uppercase tracking-[0.3em] text-ivory transition duration-500 hover:bg-transparent hover:text-cocoa disabled:cursor-default disabled:opacity-60 disabled:hover:bg-cocoa disabled:hover:text-ivory">
                        <span x-show="!submitting">{{ __('Place order') }}</span>
                        <span x-show="submitting" x-cloak>{{ __('Placing your order...') }}</span>
                    </button>
                </aside>
            </form>
        @endif
    </section>

    <x-ad-slot name="checkout_below" />
</x-store-layout>