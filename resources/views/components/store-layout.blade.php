@php
    $address = trim((string) ($settings['footer_address'] ?? ''));
    $phone = trim((string) ($settings['footer_phone'] ?? ''));
    $safeUrl = fn ($url) => is_string($url) && preg_match('#^https?://#i', trim($url)) ? trim($url) : null;
    $facebook = $safeUrl($settings['facebook_url'] ?? null);
    $instagram = $safeUrl($settings['instagram_url'] ?? null);

    // Top strip: default text until the admin saves their own; empty text hides the strip
    $topStrip = array_key_exists('top_strip_text', $settings)
        ? trim((string) $settings['top_strip_text'])
        : __('Cash on Delivery available');
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
        <div class="bg-cocoa py-2 text-center text-[11px] uppercase tracking-[0.3em] text-ivory/90">
            {{ $topStrip }}
        </div>
    @endif

    {{-- Header --}}
    <header x-data="{ open: false, scrolled: false, searching: false }"
            @scroll.window.passive="scrolled = window.scrollY > 24"
            class="sticky top-0 z-40 bg-ivory/90 backdrop-blur-md transition-colors duration-500"
            :class="scrolled ? 'border-b border-taupe/30' : 'border-b border-transparent'">

        <div class="wrap grid grid-cols-[1fr_auto_1fr] items-center transition-all duration-500"
             :class="scrolled ? 'py-3' : 'py-6 lg:py-8'">

            <div>
                <button type="button" class="-ml-1 p-1 lg:hidden" @click="open = !open; searching = false" :aria-expanded="open" aria-label="{{ __('Menu') }}">
                    <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" d="M4 8h16M4 16h16"/></svg>
                    <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <a href="{{ route('home') }}"
               class="-mr-[0.35em] font-serif font-medium uppercase tracking-[0.35em] text-cocoa transition-all duration-500"
               :class="scrolled ? 'text-2xl' : 'text-3xl lg:text-4xl'">Feminique</a>

            {{-- Right side: Search and Wishlist; Cart icon will be added here in S7 --}}
            <div class="flex items-center justify-end gap-5">
                <button type="button" class="p-1 text-cocoa transition-colors hover:text-mocha"
                        @click="searching = !searching; open = false; if (searching) $nextTick(() => $refs.q.focus())"
                        :aria-expanded="searching" aria-label="{{ __('Search') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M20 20l-3.5-3.5"/>
                    </svg>
                </button>

                <a href="{{ route('wishlist') }}" class="relative p-1 text-cocoa transition-colors hover:text-mocha" aria-label="{{ __('Wishlist') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round">
                        <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                    </svg>
                    <span x-show="$store.wishlist.count > 0" x-cloak x-text="$store.wishlist.count"
                          class="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center bg-cocoa px-1 text-[10px] leading-none text-ivory"></span>
                </a>
            </div>
        </div>

        @if ($categories->isNotEmpty())
            <nav class="hidden pb-5 lg:block" aria-label="{{ __('Categories') }}">
                <ul class="wrap flex flex-wrap items-center justify-center gap-x-12 gap-y-2 text-xs uppercase tracking-[0.28em]">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ route('category.show', $category->slug) }}"
                               class="nav-link text-cocoa/80 transition-colors hover:text-cocoa"
                               @if (request()->is('category/' . $category->slug)) aria-current="page" @endif>{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <nav x-show="open" x-cloak x-transition.opacity.duration.300ms class="border-t border-taupe/30 lg:hidden" aria-label="{{ __('Categories') }}">
                <ul class="wrap py-3 text-sm uppercase tracking-[0.2em]">
                    @foreach ($categories as $category)
                        <li class="border-b border-taupe/20 last:border-b-0">
                            <a href="{{ route('category.show', $category->slug) }}" class="block py-4 text-cocoa/90">{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        {{-- Search bar (opens under the header) --}}
        <div x-show="searching" x-cloak x-transition.opacity.duration.300ms class="border-t border-taupe/30">
            <form action="{{ route('search') }}" method="GET" role="search"
                  @keydown.escape.window="searching = false"
                  class="wrap flex items-center gap-4 py-4">
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
    </header>

    @if ($back)
        <div class="wrap pt-8 lg:pt-10">
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

            @if ($policyPages->isNotEmpty())
                <div>
                    <h2 class="text-xs uppercase tracking-[0.3em] text-ivory/60">{{ __('Information') }}</h2>
                    <ul class="mt-5 space-y-3 text-sm text-ivory/85">
                        @foreach ($policyPages as $policyPage)
                            <li><a href="{{ route('page.show', $policyPage->slug) }}" class="transition-colors hover:text-ivory">{{ $policyPage->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="border-t border-ivory/15 px-6 py-6 text-center text-xs tracking-wide text-ivory/60">
            &copy; {{ date('Y') }} Feminique. {{ __('All rights reserved.') }}
        </div>
    </footer>

</body>
</html>