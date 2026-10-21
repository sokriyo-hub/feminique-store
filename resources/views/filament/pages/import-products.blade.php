<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section heading="How to fill the file" :collapsible="true" :collapsed="true">
        <ul style="padding-left: 1.25rem; list-style: disc;">
            <li>Click "Download sample CSV" and edit it in Excel. Save as <strong>CSV UTF-8 (Comma delimited)</strong>.</li>
            <li style="margin-top: 0.4rem;">Row 1 holds the column names. Required columns: <code>name</code>, <code>category</code>, <code>price</code>, <code>stock</code>. The others are optional.</li>
            <li style="margin-top: 0.4rem;"><code>category</code> is the full path: <code>Rings</code> or <code>Rings &gt; Gold Rings</code>. It must already exist in Categories.</li>
            <li style="margin-top: 0.4rem;"><strong>Simple product:</strong> one row, leave all <code>option</code> columns empty. Use <code>stock</code> and, if you want, <code>sku</code>.</li>
            <li style="margin-top: 0.4rem;"><strong>Product with variants:</strong> one row per variant, the <strong>same name in every row</strong>. Fill <code>option1_name</code> / <code>option1_value</code> (for example Size / 7), up to 3 options. Option names must be the same in every row of that product. Up to 100 variants.</li>
            <li style="margin-top: 0.4rem;">Description, category and the product price are taken from the <strong>first row</strong>. Later rows can leave them empty.</li>
            <li style="margin-top: 0.4rem;">Every row has its own <code>stock</code> (required, 0 = sold out) and <code>sku</code>. If a variant row leaves price and discount empty, it uses the price of the first row. Fill them only for a variant with a different price.</li>
            <li style="margin-top: 0.4rem;">If any row of a product has an error, the <strong>whole product is skipped</strong> and nothing of it is added.</li>
            <li style="margin-top: 0.4rem;">Maximum 200 rows per file. With more rows nothing is added.</li>
            <li style="margin-top: 0.4rem;">Images cannot come from the CSV. Add them from the Products page after the import.</li>
        </ul>
    </x-filament::section>

    @if ($result)
        <x-filament::section heading="Import result">
            @if ($result['fatal'])
                <p>{{ $result['fatal'] }}</p>
            @else
                <p><strong>{{ $result['created'] }}</strong> product(s) added.</p>

                @if (count($result['errors']))
                    <p style="margin-top: 0.75rem;"><strong>{{ $result['skipped'] }}</strong> product(s) were skipped:</p>
                    <ul style="margin-top: 0.5rem; padding-left: 1.25rem; list-style: disc;">
                        @foreach ($result['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <p style="margin-top: 0.75rem;">Fix these rows and upload the file again. Products that were already added are skipped automatically (same name, you will see "already exists" for them), so nothing is added twice. Row 1 is the column-name row.</p>
                @else
                    <p style="margin-top: 0.5rem;">No errors. Add the product images from the Products page.</p>
                @endif
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>