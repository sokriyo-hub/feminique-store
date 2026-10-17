<x-store-layout :title="$page->title" :back="true" :back-url="route('home')">
    <article class="mx-auto max-w-3xl px-6 py-14 lg:py-20">
        <h1 class="text-center font-serif text-4xl font-light lg:text-6xl" data-reveal>{{ $page->title }}</h1>

        <div class="rich-content mt-14 text-cocoa/90" data-reveal style="--d: 120ms">
            {!! str((string) $page->content)->sanitizeHtml() !!}
        </div>
    </article>
</x-store-layout>