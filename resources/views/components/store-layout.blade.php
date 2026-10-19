@php
    $address = trim((string) ($settings['footer_address'] ?? ''));
    $phone = trim((string) ($settings['footer_phone'] ?? ''));
    $safeUrl = fn ($url) => is_string($url) && preg_match('#^https?://#i', trim($url)) ? trim($url) : null;
    $facebook = $safeUrl($settings['facebook_url'] ?? null);
    $instagram = $safeUrl($settings['instagram_url'] ?? null);
    $logo = trim((string) ($settings['site_logo'] ?? ''));

    // Top strip: default text until the admin saves their own; empty text hides the strip
    $topStrip = array_key_exists('top_strip_text', $settings)
        ? trim((string) $settings['top_strip_text'])
        : __('Cash on Delivery available');

    $iconBtn = 'relative p-1 text-cocoa transition-colors hover:text-mocha';
    $floatBox = 'border border-ivory/50 bg-ivory/55 shadow-[0_10px_30px_-12px_rgba(78,55,39,0.35)] backdrop-blur-xl';
    $dropBox = 'absolute inset-x-3 top-full mt-1 overflow-hidden rounded-3xl border border-ivory/50 bg-ivory/90 shadow-[0_10px_30px_-12px_rgba(78,55,39,0.35)] backdrop-blur-xl';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' | Feminique' : 'Feminique' }}</title>
    <meta name="description" content="{{ $description ?: __('Elegant jewellery and accessories for women.') }}">
    <script>document.documentElement.classList.add('js');</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">

    {{-- Top strip (text editable in admin Settings; empty = hidden) --}}
    @if ($topStrip !== '')
        <div class="bg-cocoa py-2 text-center text-[0.6875rem] uppercase tracking-[0.3em] text-ivory/90">
            {{ $topStrip }}
        </div>
    @endif

    {{-- Header: takes NO space (h-0). The inner box floats over the page and sticks to the top.
         See-through at the top of every page, round glass pill after scrolling. --}}
    <header x-data="{ open: false, scrolled: false, searching: false }"
            x-init="scrolled = window.scrollY > 24"
            @scroll.window.passive="scrolled = window.scrollY > 24"
            class="sticky top-0 z-40 h-0">

        <div class="relative h-20">

            {{-- Round glass pill: fades in after scrolling --}}
            <div class="pointer-events-none absolute inset-x-3 inset-y-2 rounded-full transition-opacity duration-500 lg:inset-x-6 {{ $floatBox }}"
                 :class="(scrolled || open || searching) ? 'opacity-100' : 'opacity-0'"></div>

            <div class="wrap relative grid h-full grid-cols-[1fr_auto_1fr] items-center xl:grid-cols-[auto_1fr_auto] xl:gap-8">

                {{-- Menu button (phones, tablets, small laptops) --}}
                <div class="xl:hidden">
                    <button type="button" class="-ml-1 p-1 text-cocoa" @click="open = !open; searching = false" :aria-expanded="open" aria-label="{{ __('Menu') }}">
                        <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" d="M4 8h16M4 16h16"/></svg>
                        <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                {{-- Logo (image from Settings, or text) --}}
                <a href="{{ route('home') }}" class="justify-self-center xl:justify-self-start" aria-label="Feminique">
                    @if ($logo !== '')
                        <img src="{{ asset('storage/' . $logo) }}" alt="Feminique" class="h-8 w-auto max-w-[11rem] object-contain lg:h-11 lg:max-w-[15rem]">
                    @else
                        <span class="-mr-[0.3em] font-serif text-xl font-medium uppercase tracking-[0.3em] text-cocoa sm:text-2xl xl:mr-0 xl:text-3xl xl:tracking-[0.35em]">Feminique</span>
                    @endif
                </a>

                {{-- Desktop menu: only the categories chosen in admin --}}
                @if ($navCategories->isNotEmpty())
                    <nav class="hidden min-w-0 xl:block" aria-label="{{ __('Categories') }}">
                        <ul class="flex flex-wrap items-center justify-center gap-x-7 gap-y-2 2xl:gap-x-10">
                            @foreach ($navCategories as $category)
                                <li>
                                    <a href="{{ route('category.show', $category->slug) }}"
                                       class="nav-link text-[0.9375rem] font-medium tracking-wide text-cocoa"
                                       @if (request()->is('category/' . $category->slug)) aria-current="page" @endif>{{ $category->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @else
                    <div class="hidden xl:block"></div>
                @endif

                {{-- Right side: Search, Wishlist, Cart, Track order --}}
                <div class="flex items-center justify-end gap-4 xl:gap-6">
                    <button type="button" class="{{ $iconBtn }}"
                            @click="searching = !searching; open = false; if (searching) $nextTick(() => $refs.q.focus())"
                            :aria-expanded="searching" aria-label="{{ __('Search') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="M20 20l-3.5-3.5"/>
                        </svg>
                    </button>

                    <a href="{{ route('wishlist') }}" class="{{ $iconBtn }}" aria-label="{{ __('Wishlist') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        </svg>
                        <span x-show="$store.wishlist.count > 0" x-cloak x-text="$store.wishlist.count"
                              class="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center bg-cocoa px-1 text-[0.625rem] leading-none text-ivory"></span>
                    </a>

                    <a href="{{ route('cart') }}" class="{{ $iconBtn }}" aria-label="{{ __('Cart') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2.5 3.5h2.2l2.3 11.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L20.5 7.5H5.6"/>
                            <circle cx="9.5" cy="20" r="1.2"/>
                            <circle cx="17" cy="20" r="1.2"/>
                        </svg>
                        @if ($cartCount > 0)
                            <span class="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center bg-cocoa px-1 text-[0.625rem] leading-none text-ivory">{{ $cartCount }}</span>
                        @endif
                    </a>

                    {{-- Track order (on small phones it lives in the menu) --}}
                    <a href="{{ route('track') }}" class="{{ $iconBtn }} hidden sm:block" aria-label="{{ __('Track order') }}" title="{{ __('Track order') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/>
                            <circle cx="7" cy="17.5" r="1.8"/>
                            <circle cx="17" cy="17.5" r="1.8"/>
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Menu panel: all categories + Track order (floats under the pill) --}}
            <nav x-show="open" x-cloak x-transition.opacity.duration.300ms
                 class="{{ $dropBox }} max-h-[calc(100svh-6rem)] overflow-y-auto xl:hidden"
                 aria-label="{{ __('Categories') }}">
                <ul class="px-6 py-2 text-sm uppercase tracking-[0.2em]">
                    @foreach ($categories as $category)
                        <li class="border-b border-taupe/20">
                            <a href="{{ route('category.show', $category->slug) }}" class="block py-4 text-cocoa">{{ $category->name }}</a>
                        </li>
                    @endforeach
                    <li>
                        <a href="{{ route('track') }}" class="flex items-center gap-3 py-4 text-cocoa">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/>
                                <circle cx="7" cy="17.5" r="1.8"/>
                                <circle cx="17" cy="17.5" r="1.8"/>
                            </svg>
                            {{ __('Track order') }}
                        </a>
                    </li>
                </ul>
            </nav>

            {{-- Search bar (floats under the pill) --}}
            <div x-show="searching" x-cloak x-transition.opacity.duration.300ms
                 class="{{ $dropBox }} lg:inset-x-6">
                <form action="{{ route('search') }}" method="GET" role="search"
                      @keydown.escape.window="searching = false"
                      class="flex items-center gap-4 px-6 py-4 lg:px-10">
                    <input x-ref="q" type="search" name="q" required maxlength="60" autocomplete="off"
                           placeholder="{{ __('Search jewellery') }}"
                           aria-label="{{ __('Search') }}"
                           class="min-w-0 flex-1 border-0 border-b border-taupe/60 bg-transparent py-2 font-serif text-xl text-cocoa placeholder:text-cocoa/40 focus:border-cocoa focus:outline-none focus:ring-0 lg:text-2xl">
                    <button type="submit" class="p-1 text-cocoa transition-colors hover:text-mocha" aria-label="{{ __('Search') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="M20 20l-3.5-3.5"/>
                        </svg>
                    </button>
                    <button type="button" @click="searching = false" class="p-1 text-cocoa/70 transition-colors hover:text-cocoa" aria-label="{{ __('Close') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Every page except home keeps 80px free under the floating header --}}
    @unless ($overlay)
        <div class="h-20" aria-hidden="true"></div>
    @endunless

    {{-- "Added to cart" message --}}
    @if (session('cart_notice'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.opacity.duration.500ms
             class="fixed inset-x-0 top-24 z-50 flex justify-center px-4" role="status">
            <div class="flex items-center gap-6 border border-cocoa bg-cocoa px-6 py-4 text-sm text-ivory shadow-lg">
                <span>{{ session('cart_notice') }}</span>
                <a href="{{ route('cart') }}" class="border-b border-ivory/60 pb-0.5 text-xs uppercase tracking-[0.25em] transition-colors hover:border-ivory">{{ __('View cart') }}</a>
            </div>
        </div>
    @endif

    @if ($back)
        <div class="wrap pt-3 lg:pt-4">
            <x-back-link :fallback="$backUrl" />
        </div>
    @endif

    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="mt-24 bg-cocoa text-ivory">
        <div class="wrap grid gap-14 py-16 lg:grid-cols-[2fr_1fr_1fr]">
            <div>
                <p class="font-serif text-3xl font-medium uppercase tracking-[0.35em]">Feminique</p>
                <p class="mt-5 max-w-sm text-sm leading-relaxed text-ivory/70">{{ __('Jewellery and accessories for women.') }}</p>
            </div>

            <div>
                <h2 class="text-xs uppercase tracking-[0.3em] text-ivory/60">{{ __('Contact') }}</h2>
                <div class="mt-5 space-y-3 text-sm leading-relaxed text-ivory/85">
                    @if ($address !== '')
                        <p class="whitespace-pre-line">{{ $address }}</p>
                    @endif
                    @if ($phone !== '')
                        <p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="transition-colors hover:text-ivory">{{ $phone }}</a></p>
                    @endif
                    @if ($facebook || $instagram)
                        <p class="flex gap-6 pt-2 text-xs uppercase tracking-[0.25em]">
                            @if ($facebook)
                                <a href="{{ $facebook }}" target="_blank" rel="noopener noreferrer" class="border-b border-ivory/40 pb-0.5 transition-colors hover:border-ivory">Facebook</a>
                            @endif
                            @if ($instagram)
                                <a href="{{ $instagram }}" target="_blank" rel="noopener noreferrer" class="border-b border-ivory/40 pb-0.5 transition-colors hover:border-ivory">Instagram</a>
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            <div>
                <h2 class="text-xs uppercase tracking-[0.3em] text-ivory/60">{{ __('Information') }}</h2>
                <ul class="mt-5 space-y-3 text-sm text-ivory/85">
                    <li><a href="{{ route('track') }}" class="transition-colors hover:text-ivory">{{ __('Track your order') }}</a></li>
                    @foreach ($policyPages as $policyPage)
                        <li><a href="{{ route('page.show', $policyPage->slug) }}" class="transition-colors hover:text-ivory">{{ $policyPage->title }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="border-t border-ivory/15 px-6 py-6 text-center text-xs tracking-wide text-ivory/60">
            &copy; {{ date('Y') }} Feminique. {{ __('All rights reserved.') }}
        </div>
    </footer>

</body>
</html>