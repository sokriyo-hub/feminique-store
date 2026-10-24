@props(['campaign'])
@php
    $pct = (int) $campaign->discount_percent;
    $title = trim((string) $campaign->banner_title) !== '' ? trim($campaign->banner_title) : $campaign->name;
    $text = trim((string) $campaign->banner_text);
    $button = trim((string) $campaign->banner_button);
    $image = $campaign->banner_image;
    $box = 'rounded-lg border border-ivory/30 bg-cocoa/40 px-2.5 py-1.5 text-center backdrop-blur-sm sm:min-w-[4.25rem] sm:px-4 sm:py-2';
@endphp

{{-- Campaign banner: same height as a card picture, rounded box, optional background image, countdown.
     When the countdown reaches zero the page reloads once, so the normal prices come back. --}}
<section class="wrap ad-wrap py-8 lg:py-10" data-reveal>
    <div class="ad-frame relative isolate flex items-center overflow-hidden rounded-2xl bg-cocoa text-ivory"
         x-data="{
             end: {{ $campaign->ends_at->getTimestamp() * 1000 }},
             skew: {{ now()->getTimestamp() * 1000 }} - Date.now(),
             now: Date.now(),
             init() {
                 this.now = Date.now() + this.skew;
                 const timer = setInterval(() => {
                     this.now = Date.now() + this.skew;
                     if (this.left <= 0) { clearInterval(timer); this.finish(); }
                 }, 1000);
             },
             get left() { return Math.max(0, Math.floor((this.end - this.now) / 1000)); },
             get days() { return Math.floor(this.left / 86400); },
             get hours() { return Math.floor((this.left % 86400) / 3600); },
             get mins() { return Math.floor((this.left % 3600) / 60); },
             get secs() { return this.left % 60; },
             pad(n) { return String(n).padStart(2, '0'); },
             finish() {
                 try {
                     const key = 'fq_campaign_reload';
                     if (Date.now() - Number(sessionStorage.getItem(key) || 0) > 15000) {
                         sessionStorage.setItem(key, String(Date.now()));
                         setTimeout(() => location.reload(), 1500);
                     }
                 } catch (e) {}
             }
         }">

        @if ($image)
            <img src="{{ asset('storage/' . $image) }}" alt="" loading="lazy" draggable="false"
                 class="absolute inset-0 -z-10 h-full w-full object-cover">
            <div class="absolute inset-0 -z-10 bg-linear-to-r from-cocoa/90 via-cocoa/60 to-cocoa/20"></div>
        @endif

        <div class="w-full px-5 py-5 sm:px-10 lg:px-14">
            <div class="flex flex-wrap items-center gap-2 text-[0.625rem] uppercase tracking-[0.3em] sm:gap-3 sm:text-[0.6875rem]">
                <span class="rounded-full bg-ivory px-3 py-1 font-medium text-cocoa sm:px-4 sm:py-1.5">{{ $pct }}% {{ __('off') }}</span>
                @if ($campaign->free_delivery)
                    <span class="rounded-full border border-ivory/60 px-3 py-1 sm:px-4 sm:py-1.5">{{ __('Free delivery') }}</span>
                @endif
            </div>

            <h2 class="mt-3 max-w-2xl font-serif text-2xl font-light leading-tight sm:text-3xl lg:text-4xl">{{ $title }}</h2>

            @if ($text !== '')
                <p class="mt-2 hidden max-w-xl whitespace-pre-line text-sm leading-relaxed text-ivory/85 sm:block">{{ $text }}</p>
            @endif

            @if ($campaign->show_countdown || $button !== '')
                <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-3">
                    @if ($campaign->show_countdown)
                        <div class="flex items-center gap-2 sm:gap-3" role="timer">
                            <span class="hidden text-[0.625rem] uppercase tracking-[0.3em] text-ivory/70 sm:inline">{{ __('Ends in') }}</span>
                            <div class="{{ $box }}">
                                <p class="font-serif text-xl leading-none sm:text-2xl" x-text="pad(days)">00</p>
                                <p class="mt-1 text-[0.5625rem] uppercase tracking-[0.2em] text-ivory/70">{{ __('Days') }}</p>
                            </div>
                            <div class="{{ $box }}">
                                <p class="font-serif text-xl leading-none sm:text-2xl" x-text="pad(hours)">00</p>
                                <p class="mt-1 text-[0.5625rem] uppercase tracking-[0.2em] text-ivory/70">{{ __('Hours') }}</p>
                            </div>
                            <div class="{{ $box }}">
                                <p class="font-serif text-xl leading-none sm:text-2xl" x-text="pad(mins)">00</p>
                                <p class="mt-1 text-[0.5625rem] uppercase tracking-[0.2em] text-ivory/70">{{ __('Min') }}</p>
                            </div>
                            <div class="{{ $box }}">
                                <p class="font-serif text-xl leading-none sm:text-2xl" x-text="pad(secs)">00</p>
                                <p class="mt-1 text-[0.5625rem] uppercase tracking-[0.2em] text-ivory/70">{{ __('Sec') }}</p>
                            </div>
                        </div>
                    @endif

                    @if ($button !== '')
                        <a href="#collections"
                           class="inline-flex items-center gap-3 rounded-full border border-ivory bg-ivory px-6 py-2.5 text-xs font-medium uppercase tracking-[0.25em] text-cocoa transition duration-500 hover:bg-transparent hover:text-ivory">
                            {{ $button }}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h18M15 6l6 6-6 6"/></svg>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>