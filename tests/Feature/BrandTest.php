<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Brand;
use App\Support\CampaignPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Brand::flush();
        CampaignPricing::flush();
    }

    public function test_the_defaults_are_the_heshelmart_brand(): void
    {
        $this->assertSame('HeshelMart', Brand::name());
        $this->assertSame('Good Food • Better Living', Brand::tagline());
        $this->assertSame('#07502F', Brand::color('brand_color_primary'));
        $this->assertSame('', (string) Brand::styleTag());
    }

    public function test_a_saved_name_is_used_and_an_empty_tagline_is_hidden(): void
    {
        Setting::put('brand_name', ' Test Shop ');
        Setting::put('brand_tagline', null);
        Brand::flush();

        $this->assertSame('Test Shop', Brand::name());
        $this->assertSame('', Brand::tagline());
        $this->assertSame('Test Shop', Brand::description());
    }

    public function test_a_custom_color_is_written_as_css_variables_with_a_darker_shade(): void
    {
        Setting::put('brand_color_primary', '#112233');
        Brand::flush();

        $css = (string) Brand::styleTag();

        $this->assertStringContainsString('--color-forest: #112233', $css);
        $this->assertStringContainsString('--color-cocoa: #112233', $css);
        $this->assertStringContainsString('--color-forest-dark: #0D1A26', $css);
    }

    public function test_a_bad_color_value_is_ignored(): void
    {
        Setting::put('brand_color_primary', 'red;} </style><script>alert(1)</script>');
        Brand::flush();

        $this->assertSame('#07502F', Brand::color('brand_color_primary'));
        $this->assertSame('', (string) Brand::styleTag());
    }

    public function test_the_footer_shows_the_brand_name_and_tagline(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('HeshelMart')
            ->assertSee('Good Food • Better Living')
            ->assertDontSee('Feminique');
    }
}