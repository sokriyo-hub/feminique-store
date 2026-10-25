<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCookieTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create(['name' => 'Rings', 'slug' => 'rings', 'sort_order' => 1]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Cookie Ring',
            'slug' => 'cookie-ring',
            'price' => 1000,
            'has_variants' => false,
            'stock' => 5,
        ]);
    }

    public function test_adding_to_cart_saves_a_30_day_cookie(): void
    {
        $product = $this->product();

        $response = $this->post('/cart/add', ['product_id' => $product->id, 'quantity' => 2]);

        $cookie = $response->getCookie('fq_cart', false);

        $this->assertNotNull($cookie);

        $expected = now()->addDays(30)->getTimestamp();
        $this->assertLessThan(300, abs($cookie->getExpiresTime() - $expected));
    }

    public function test_the_cart_page_reads_the_cookie(): void
    {
        $product = $this->product();

        $this->withCookie('fq_cart', json_encode([$product->id . '-0' => 2]))
            ->get('/cart')
            ->assertOk()
            ->assertSee('Cookie Ring');
    }

    public function test_an_old_session_cart_moves_to_the_cookie(): void
    {
        $product = $this->product();

        $response = $this->withSession(['cart_v2' => [$product->id . '-0' => 1]])->get('/cart');

        $response->assertOk()->assertSee('Cookie Ring');
        $this->assertNotNull($response->getCookie('fq_cart', false));
    }

    public function test_a_broken_cookie_gives_an_empty_cart_not_an_error(): void
    {
        $this->withCookie('fq_cart', 'not-json-at-all')
            ->get('/cart')
            ->assertOk()
            ->assertSee('Your cart is empty.');
    }
}