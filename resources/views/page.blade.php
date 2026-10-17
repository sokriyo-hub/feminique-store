<x-store-layout :title="$page->title">
    <article class="mx-auto max-w-3xl px-6 py-16 lg:py-24">
        <h1 class="text-center font-serif text-4xl font-light lg:text-6xl">{{ $page->title }}</h1>

        <div class="rich-content mt-14 text-cocoa/90">
            {!! str((string) $page->content)->sanitizeHtml() !!}
        </div>
    </article>
</x-store-layout>