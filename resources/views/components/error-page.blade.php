@props(['code', 'title', 'message', 'retry' => false])

<x-store-layout :title="$title">
    <section class="mx-auto max-w-2xl px-6 py-20 text-center lg:py-28">
        <p class="font-serif text-7xl font-light tracking-[0.2em] text-cocoa/30 lg:text-8xl" data-reveal>{{ $code }}</p>

        <h1 class="mt-6 font-serif text-3xl font-light text-cocoa lg:text-5xl" data-reveal style="--d: 100ms">{{ $title }}</h1>

        <p class="mx-auto mt-5 max-w-md text-sm leading-relaxed text-cocoa/80" data-reveal style="--d: 180ms">{{ $message }}</p>

        <div class="mt-10 flex flex-wrap justify-center gap-3" data-reveal style="--d: 260ms">
            <a href="{{ route('home') }}"
               class="border border-cocoa bg-cocoa px-8 py-4 text-xs font-medium uppercase tracking-[0.25em] text-ivory transition duration-500 hover:bg-transparent hover:text-cocoa">{{ __('Back to home') }}</a>

            @if ($retry)
                <button type="button" x-data @click="window.history.back()"
                        class="border border-cocoa/40 bg-transparent px-8 py-4 text-xs font-medium uppercase tracking-[0.25em] text-cocoa transition duration-500 hover:bg-cocoa hover:text-ivory">{{ __('Go back') }}</button>
            @endif
        </div>
    </section>
</x-store-layout>