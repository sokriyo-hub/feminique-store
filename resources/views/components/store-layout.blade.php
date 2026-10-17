@php
    $address = trim((string) ($settings['footer_address'] ?? ''));
    $phone = trim((string) ($settings['footer_phone'] ?? ''));
    $safeUrl = fn ($url) => is_string($url) && preg_match('#^https?://#i', trim($url)) ? trim($url) : null;
    $facebook = $safeUrl($settings['facebook_url'] ?? null);
    $instagram = $safeUrl($settings['instagram_url'] ?? null);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' | Feminique' : 'Feminique' }}</title>
    <meta name="description" content="{{ $description ?: __('Elegant jewellery and accessories for women.') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">

    {{-- Header --}}
    <header class="border-b border-taupe/30" x-data="{ open: false }">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:justify-center lg:px-10 lg:py-8">
            <button type="button" class="-ml-1 p-1 lg:hidden" @click="open = !open" :aria-expanded="open" aria-label="{{ __('Menu') }}">
                <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" d="M4 8h16M4 16h16"/></svg>
                <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>

            <a href="{{ route('home') }}" class="-mr-[0.35em] font-serif text-3xl font-medium uppercase tracking-[0.35em] text-cocoa lg:text-4xl">Feminique</a>

            <span class="w-6 lg:hidden" aria-hidden="true"></span>
        </div>

        @if ($categories->isNotEmpty())
            <nav class="hidden pb-6 lg:block" aria-label="{{ __('Categories') }}">
                <ul class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-10 gap-y-3 px-10 text-xs uppercase tracking-[0.25em]">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ route('category.show', $category->slug) }}" class="border-b border-transparent py-1 text-cocoa/80 transition hover:border-cocoa hover:text-cocoa">{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <nav x-show="open" x-cloak x-transition.opacity class="border-t border-taupe/30 lg:hidden" aria-label="{{ __('Categories') }}">
                <ul class="px-6 py-4 text-sm uppercase tracking-[0.2em]">
                    @foreach ($categories as $category)
                        <li class="border-b border-taupe/20 last:border-b-0">
                            <a href="{{ route('category.show', $category->slug) }}" class="block py-4 text-cocoa/90">{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="mt-24 bg-cocoa text-ivory">
        <div class="mx-auto grid max-w-7xl gap-14 px-6 py-16 lg:grid-cols-[2fr_1fr_1fr] lg:px-10">
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
                        <p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="transition hover:text-ivory">{{ $phone }}</a></p>
                    @endif
                    @if ($facebook || $instagram)
                        <p class="flex gap-6 pt-2 text-xs uppercase tracking-[0.25em]">
                            @if ($facebook)
                                <a href="{{ $facebook }}" target="_blank" rel="noopener noreferrer" class="border-b border-ivory/40 pb-0.5 transition hover:border-ivory">Facebook</a>
                            @endif
                            @if ($instagram)
                                <a href="{{ $instagram }}" target="_blank" rel="noopener noreferrer" class="border-b border-ivory/40 pb-0.5 transition hover:border-ivory">Instagram</a>
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
                            <li><a href="{{ route('page.show', $policyPage->slug) }}" class="transition hover:text-ivory">{{ $policyPage->title }}</a></li>
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