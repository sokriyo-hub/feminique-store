<x-filament-panels::page>
    <x-filament::section heading="Database">
        <ul style="padding-left: 1.25rem; list-style: disc;">
            <li>Database size: <strong>{{ $dbSize ?? 'unknown' }}</strong></li>
            <li style="margin-top: 0.4rem;">Orders in the database: <strong>{{ number_format($ordersTotal) }}</strong></li>
            <li style="margin-top: 0.4rem;">Orders that can be deleted now (Delivered or Cancelled, older than {{ $minDays }} days): <strong>{{ number_format($deletable) }}</strong></li>
        </ul>
    </x-filament::section>

    <x-filament::section heading="Export CSV" :collapsible="true">
        <ul style="padding-left: 1.25rem; list-style: disc;">
            <li><strong>Export products:</strong> the same format as "Import products" (one row per variant). You can edit it in Excel and import new products from it.</li>
            <li style="margin-top: 0.4rem;"><strong>Export categories:</strong> the full list with paths, for your records.</li>
            <li style="margin-top: 0.4rem;"><strong>Export orders:</strong> one row per ordered item. Subtotal, delivery charge and order total are written only on the first row of each order, so adding up <code>order_total</code> never counts an order twice.</li>
            <li style="margin-top: 0.4rem;">Excel may hide the leading 0 of phone numbers when you double-click the file. The file itself has the full number. To see it, use Excel's Data &gt; From Text/CSV.</li>
            <li style="margin-top: 0.4rem;">Images, Settings, pages and delivery zones are not in these files. A CSV export is not a full backup of the website.</li>
        </ul>
    </x-filament::section>

    <x-filament::section heading="Delete old orders" :collapsible="true">
        <ul style="padding-left: 1.25rem; list-style: disc;">
            <li>Use it when the database gets full: first <strong>Export orders</strong> and keep the file safe, then <strong>Delete old orders</strong>.</li>
            <li style="margin-top: 0.4rem;">Only <strong>Delivered</strong> and <strong>Cancelled</strong> orders older than <strong>{{ $minDays }} days</strong> can be deleted. Pending, Confirmed and Shipped orders are never deleted.</li>
            <li style="margin-top: 0.4rem;">Stock is not changed by deleting an order.</li>
            <li style="margin-top: 0.4rem;">After deleting, some dashboard numbers that count all orders ever (Delivered amount, Customers, Average order value, Top selling products, Sales by category) become smaller. Customers can no longer track a deleted order.</li>
        </ul>
    </x-filament::section>

    <x-filament::section heading="Clear cache" :collapsible="true" :collapsed="true">
        <p>Clears the site's temporary data and prepares the pages again. You stay logged in and nothing in products or orders changes. It also resets the "too many attempts" block on the order tracking page. Use it if the site shows old content after a change.</p>
    </x-filament::section>
</x-filament-panels::page>