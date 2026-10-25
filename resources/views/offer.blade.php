<x-store-layout :title="$campaign->pageTitle()" :back="true" :back-url="route('home')">
    <section class="wrap pb-6 pt-4 lg:pb-8 lg:pt-6">
        <div class="text-center" data-reveal>
            <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Special offer') }}</p>
            <h1 class="mt-4 font-serif text-4xl font-light lg:text-6xl">{{ $campaign->pageTitle() }}</h1>

            @if ($running)
                <div class="mt-5 flex flex-wrap items-center justify-center gap-3 text-[0.6875rem] uppercase tracking-[0.3em]">
                    <span class="rounded-full bg-cocoa px-4 py-1.5 font-medium text-ivory">{{ (int) $campaign->discount_percent }}% {{ __('off') }}</span>
                    @if ($campaign->free_delivery)
                        <span class="rounded-full border border-cocoa/50 px-4 py-1.5">{{ __('Free delivery') }}</span>
                    @endif
                </div>
                <p class="mt-4 text-sm text-cocoa/70">
                    {{ __('Ends on :date', ['date' => $campaign->ends_at->format('d M Y, h:i A')]) }}
                    &middot;
                    {{ trans_choice('{0} :count pieces|{1} :count piece|[2,*] :count pieces', $products->total()) }}
                </p>
            @endif
        </div>
    </section>

    <section class="wrap pb-12 lg:pb-20">
        @if (! $running)
            <div class="mt-6 text-center">
                <p class="text-cocoa/80">{{ __('This offer is not running right now.') }}</p>
                <a href="{{ route('home') }}" class="mt-8 inline-block border-b border-cocoa pb-1 text-xs uppercase tracking-[0.3em]">{{ __('Back to home') }}</a>
            </div>
        @elseif ($products->isEmpty())
            <div class="mt-6 text-center">
                <p class="text-cocoa/80">{{ __('No products in this offer yet.') }}</p>
                <a href="{{ route('home') }}" class="mt-8 inline-block border-b border-cocoa pb-1 text-xs uppercase tracking-[0.3em]">{{ __('Back to home') }}</a>
            </div>
        @else
            <div class="mt-2 grid grid-cols-2 gap-x-5 gap-y-12 md:grid-cols-4 lg:grid-cols-5 lg:gap-x-8 2xl:grid-cols-6">
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