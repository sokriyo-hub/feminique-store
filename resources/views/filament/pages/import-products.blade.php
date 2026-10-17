<x-filament-panels::page>
    {{ $this->form }}

    @if ($result)
        <x-filament::section heading="Import result">
            @if ($result['fatal'])
                <p>{{ $result['fatal'] }}</p>
            @else
                <p><strong>{{ $result['created'] }}</strong> product(s) added.</p>

                @if (count($result['errors']))
                    <p style="margin-top: 0.75rem;"><strong>{{ count($result['errors']) }}</strong> row(s) were skipped:</p>
                    <ul style="margin-top: 0.5rem; padding-left: 1.25rem; list-style: disc;">
                        @foreach ($result['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <p style="margin-top: 0.75rem;">Fix these rows and upload the file again. Rows that were already added are skipped automatically (same name), so nothing is added twice. Row 1 is the column-name row.</p>
                @else
                    <p style="margin-top: 0.5rem;">No errors. Add the product images from the Products page.</p>
                @endif
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>