@props(['fallback' => null, 'label' => null])

<a href="{{ $fallback ?? route('home') }}"
   x-data
   @click="if (document.referrer && new URL(document.referrer).origin === window.location.origin && window.history.length > 1) { $event.preventDefault(); window.history.back(); }"
   class="nav-link inline-flex items-center gap-3 text-xs uppercase tracking-[0.25em] text-cocoa/80 transition-colors hover:text-cocoa">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m6-6l-6 6 6 6"/></svg>
    {{ $label ?? __('Back') }}
</a>