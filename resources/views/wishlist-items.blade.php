@foreach ($products as $product)
    <x-product-card :product="$product" :reveal="false" />
@endforeach