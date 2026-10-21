<x-store-layout :title="$category->name" :back="true" :back-url="$backUrl">
    <section class="wrap pb-6 pt-4 lg:pb-8 lg:pt-6">
        <div class="text-center" data-reveal>
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Collection') }}</p>
            <h1 class="mt-4 font-serif text-4xl font-light lg:text-6xl">{{ $category->name }}</h1>
            <p class="mt-4 text-sm text-cocoa/70">{{ trans_choice('{0} :count pieces|{1} :count piece|[2,*] :count pieces', $products->total()) }}</p>
        </div>
    </section>

    {{-- Sub-categories: one scroll row, like the home page --}}
    @if ($children->isNotEmpty())
        <x-category-scroller :categories="$children" />
    @endif

    <section class="wrap pb-12 lg:pb-20 {{ $children->isNotEmpty() ? 'pt-6' : '' }}">

        {{-- Customer Favorites in this category (only when it has sub-categories) --}}
        @if ($popular->isNotEmpty())
            <div class="mb-16">
                <div class="text-center" data-reveal>
                    <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Most loved') }}</p>
                    <h2 class="mt-4 font-serif text-3xl font-light lg:text-5xl">{{ __('Customer Favorites in :name', ['name' => $category->name]) }}</h2>
                </div>

                <div class="mt-10 grid grid-cols-2 gap-x-5 gap-y-12 md:grid-cols-4 lg:grid-cols-5 lg:gap-x-8 2xl:grid-cols-6">
                    @foreach ($popular as $product)
                        <x-product-card :product="$product" :delay="($loop->index % 5) * 80" />
                    @endforeach
                </div>
            </div>
        @endif

        @if ($products->isEmpty())
            @if ($children->isEmpty())
                <div class="mt-6 text-center">
                    <p class="text-cocoa/80">{{ __('No products in this category yet.') }}</p>
                    <a href="{{ route('home') }}" class="mt-8 inline-block border-b border-cocoa pb-1 text-xs uppercase tracking-[0.3em]">{{ __('Back to home') }}</a>
                </div>
            @endif
        @else
            @if ($children->isNotEmpty())
                <div class="text-center" data-reveal>
                    <h2 class="font-serif text-3xl font-light lg:text-4xl">{{ __('All pieces') }}</h2>
                </div>
            @endif

            <div class="{{ $children->isNotEmpty() ? 'mt-10' : 'mt-2' }} grid grid-cols-2 gap-x-5 gap-y-12 md:grid-cols-4 lg:grid-cols-5 lg:gap-x-8 2xl:grid-cols-6">
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