<x-store-layout :title="__('Search')" :back="true" :back-url="route('home')">
    <section class="wrap py-12 lg:py-20">
        <div class="text-center" data-reveal>
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Search') }}</p>

            @if ($term !== '')
                <h1 class="mt-4 font-serif text-4xl font-light lg:text-6xl">&ldquo;{{ $term }}&rdquo;</h1>
                @if ($products)
                    <p class="mt-4 text-sm text-cocoa/70">{{ trans_choice('{0} :count pieces|{1} :count piece|[2,*] :count pieces', $products->total()) }}</p>
                @endif
            @else
                <h1 class="mt-4 font-serif text-4xl font-light lg:text-6xl">{{ __('Find your piece') }}</h1>
            @endif

            {{-- Search again --}}
            <form action="{{ route('search') }}" method="GET" role="search" class="mx-auto mt-10 flex max-w-xl items-center gap-4">
                <input type="search" name="q" value="{{ $term }}" required maxlength="60" autocomplete="off"
                       placeholder="{{ __('Search jewellery') }}"
                       aria-label="{{ __('Search') }}"
                       class="min-w-0 flex-1 border-0 border-b border-taupe/60 bg-transparent py-2 text-center font-serif text-xl text-cocoa placeholder:text-cocoa/40 focus:border-cocoa focus:outline-none focus:ring-0">
                <button type="submit" class="border border-cocoa px-6 py-2.5 text-xs font-medium uppercase tracking-[0.25em] text-cocoa transition duration-500 hover:bg-cocoa hover:text-ivory">{{ __('Search') }}</button>
            </form>
        </div>

        @if ($products === null)
            @if ($term !== '')
                <p class="mt-16 text-center text-cocoa/80">{{ __('Type at least 2 characters to search.') }}</p>
            @endif
        @elseif ($products->isEmpty())
            <div class="mt-16 text-center">
                <p class="text-cocoa/80">{{ __('No pieces found for your search.') }}</p>
                <a href="{{ route('home') }}#collections" class="mt-8 inline-block border-b border-cocoa pb-1 text-xs uppercase tracking-[0.3em]">{{ __('Browse collections') }}</a>
            </div>
        @else
            <div class="mt-16 grid grid-cols-2 gap-x-5 gap-y-12 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-8 2xl:grid-cols-5">
                @foreach ($products as $product)
                    <x-product-card :product="$product" :delay="($loop->index % 5) * 80" />
                @endforeach
            </div>

            @if ($products->hasPages())
                <nav class="mt-20 flex items-center justify-center gap-10 text-xs uppercase tracking-[0.25em]" aria-label="{{ __('Pagination') }}">
                    @if ($products->onFirstPage())
                        <span class="text-cocoa/30">{{ __('Previous') }}</span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}" class="nav-link">{{ __('Previous') }}</a>
                    @endif

                    <span class="text-cocoa/70">{{ $products->currentPage() }} / {{ $products->lastPage() }}</span>

                    @if ($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}" class="nav-link">{{ __('Next') }}</a>
                    @else
                        <span class="text-cocoa/30">{{ __('Next') }}</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
</x-store-layout>