<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_page_shows_the_404_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertStatus(404)
            ->assertSee('Page not found');
    }

    public function test_419_and_429_use_the_store_pages(): void
    {
        Route::get('/_test/419', fn () => abort(419));
        Route::get('/_test/429', fn () => abort(429));

        $this->get('/_test/419')->assertStatus(419)->assertSee('Session expired');
        $this->get('/_test/429')->assertStatus(429)->assertSee('Too many requests');
    }

    public function test_500_and_503_pages_render(): void
    {
        Route::get('/_test/500', fn () => abort(500));
        Route::get('/_test/503', fn () => abort(503));

        $this->get('/_test/500')->assertStatus(500)->assertSee('Something went wrong');
        $this->get('/_test/503')->assertStatus(503)->assertSee('We will be right back');
    }

    // 500 and 503 must not touch the database (it may be the thing that is broken)
    public function test_500_and_503_views_do_not_need_the_database(): void
    {
        $this->assertStringContainsString('Feminique', view('errors.500')->render());
        $this->assertStringContainsString('Feminique', view('errors.503')->render());
    }
}