<div x-data="{ show: window.location.pathname.replace(/\/+$/, '') !== '/admin' }" x-show="show" x-cloak style="margin-bottom: 0.75rem;">
    <x-filament::link
        tag="a"
        href="{{ url('/admin') }}"
        icon="heroicon-m-arrow-left"
        color="gray"
        size="sm"
        x-on:click="if (document.referrer && new URL(document.referrer).origin === window.location.origin && history.length > 1) { $event.preventDefault(); history.back(); }">
        Back
    </x-filament::link>
</div>