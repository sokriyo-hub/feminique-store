<x-store-layout :title="__('Wishlist')"
                :description="__('Your saved pieces.')"
                :back="true"
                :back-url="route('home')">
    <section class="wrap py-10 lg:py-14" x-data="wishlistPage('{{ route('wishlist.items') }}')">

        <div class="text-center" data-reveal>
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Saved') }}</p>
            <h1 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('Wishlist') }}</h1>
        </div>

        <p x-show="loading" x-cloak class="mt-16 text-center text-xs uppercase tracking-[0.3em] text-cocoa/60">{{ __('Loading') }}</p>

        <p x-show="failed && !loading" x-cloak class="mt-16 text-center text-sm text-cocoa/80">{{ __('Could not load your wishlist. Please refresh the page.') }}</p>

        <div x-show="!loading && !failed && $store.wishlist.count === 0" x-cloak class="mt-16 text-center">
            <p class="font-serif text-2xl text-cocoa">{{ __('Your wishlist is empty.') }}</p>
            <p class="mt-3 text-sm text-cocoa/70">{{ __('Tap the heart on any piece to save it here.') }}</p>
            <a href="{{ route('home') }}#collections" class="mt-8 inline-block border border-cocoa bg-transparent px-10 py-4 text-xs font-medium uppercase tracking-[0.3em] text-cocoa transition duration-500 hover:bg-cocoa hover:text-ivory">{{ __('Continue shopping') }}</a>
        </div>

        <div x-ref="list" x-html="html"
             class="mt-14 grid grid-cols-2 gap-x-5 gap-y-12 md:grid-cols-4 lg:grid-cols-5 lg:gap-x-8 2xl:grid-cols-6"></div>
    </section>
</x-store-layout>