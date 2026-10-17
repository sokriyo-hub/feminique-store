<x-store-layout :description="__('Elegant jewellery and accessories for women.')">

    {{-- Hero --}}
    @if ($heroPath)
        <section class="relative h-[70vh] min-h-[420px] w-full overflow-hidden">
            <img src="{{ asset('storage/' . $heroPath) }}" alt="Feminique" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-cocoa/25"></div>
            <div class="relative flex h-full items-center justify-center px-6">
                <a href="#collections" class="border border-ivory bg-ivory px-10 py-4 text-xs font-medium uppercase tracking-[0.3em] text-cocoa transition duration-500 hover:bg-transparent hover:text-ivory">{{ __('Shop Now') }}</a>
            </div>
        </section>
    @else
        <section class="flex h-[60vh] min-h-[380px] w-full flex-col items-center justify-center bg-beige px-6 text-center">
            <p class="font-serif text-5xl font-light uppercase tracking-[0.3em] text-cocoa lg:text-7xl">Feminique</p>
            <a href="#collections" class="mt-10 border border-cocoa bg-cocoa px-10 py-4 text-xs font-medium uppercase tracking-[0.3em] text-ivory transition duration-500 hover:bg-transparent hover:text-cocoa">{{ __('Shop Now') }}</a>
        </section>
    @endif

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <section id="collections" class="mx-auto max-w-7xl scroll-mt-8 px-6 py-24 lg:px-10">
            <div class="text-center">
                <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Collections') }}</p>
                <h2 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('Shop by Category') }}</h2>
            </div>

            <div class="mt-14 grid grid-cols-2 gap-4 md:grid-cols-3 lg:gap-6">
                @foreach ($categories as $category)
                    <a href="{{ route('category.show', $category->slug) }}"
                       class="flex aspect-[4/3] flex-col items-center justify-center border border-taupe/40 bg-beige/40 px-4 text-center transition duration-500 hover:bg-beige">
                        <span class="font-serif text-2xl leading-tight lg:text-3xl">{{ $category->name }}</span>
                        <span class="mt-3 text-[11px] uppercase tracking-[0.25em] text-cocoa/70">{{ trans_choice('{0} :count pieces|{1} :count piece|[2,*] :count pieces', $category->products_count) }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- New Arrivals --}}
    @if ($newArrivals->isNotEmpty())
        <section id="new-arrivals" class="mx-auto max-w-7xl px-6 pb-8 lg:px-10">
            <div class="text-center">
                <p class="text-xs uppercase tracking-[0.35em] text-cocoa/70">{{ __('Just in') }}</p>
                <h2 class="mt-4 font-serif text-4xl font-light lg:text-5xl">{{ __('New Arrivals') }}</h2>
            </div>

            <div class="mt-14 grid grid-cols-2 gap-x-5 gap-y-12 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-8">
                @foreach ($newArrivals as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

</x-store-layout>