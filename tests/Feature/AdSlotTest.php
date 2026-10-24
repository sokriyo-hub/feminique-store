<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Campaign;
use App\Models\Setting;
use App\Support\AdSlots;
use App\Support\CampaignPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdSlotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetCaches();
    }

    private function resetCaches(): void
    {
        Ad::flushCache();
        AdSlots::flush();
        CampaignPricing::flush();
    }

    private function ad(array $attributes = []): Ad
    {
        $ad = Ad::create($attributes + [
            'name' => 'Test ad',
            'media' => 'ads/test.webp',
            'title' => 'Hello ad',
            'text_color' => 'light',
            'text_align' => 'left',
            'placements' => ['home_after_categories'],
            'is_active' => true,
        ]);

        $this->resetCaches();

        return $ad;
    }

    private function campaign(array $attributes = []): Campaign
    {
        $campaign = Campaign::create($attributes + [
            'name' => 'Test sale',
            'discount_percent' => 11,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'all_products' => true,
            'banner_title' => 'Big sale banner',
            'banner_slots' => ['home_after_categories'],
        ]);

        $this->resetCaches();

        return $campaign;
    }

    public function test_an_ad_is_shown_only_in_its_own_place(): void
    {
        $this->ad();

        $this->blade('<x-ad-slot name="home_after_categories" />')->assertSee('Hello ad');
        $this->blade('<x-ad-slot name="home_after_hero" />')->assertDontSee('Hello ad');
    }

    public function test_an_ad_can_be_shown_in_several_places(): void
    {
        $this->ad(['placements' => ['checkout_above', 'checkout_below', 'cart_bottom']]);

        $this->blade('<x-ad-slot name="checkout_above" />')->assertSee('Hello ad');
        $this->blade('<x-ad-slot name="checkout_below" />')->assertSee('Hello ad');
        $this->blade('<x-ad-slot name="cart_bottom" />')->assertSee('Hello ad');
    }

    public function test_a_place_that_is_switched_off_shows_nothing(): void
    {
        $this->ad();

        Setting::put('ad_slots_off', json_encode(['home_after_categories']));
        $this->resetCaches();

        $this->blade('<x-ad-slot name="home_after_categories" />')->assertDontSee('Hello ad');
    }

    public function test_ads_that_are_off_or_outside_their_dates_are_not_shown(): void
    {
        foreach ([
            ['is_active' => false],
            ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)],
            ['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()],
        ] as $attributes) {
            Ad::query()->delete();
            $this->ad($attributes);

            $this->blade('<x-ad-slot name="home_after_categories" />')->assertDontSee('Hello ad');
        }
    }

    public function test_if_two_old_ads_share_a_place_the_oldest_is_shown(): void
    {
        $this->ad(['name' => 'First', 'title' => 'First ad']);
        $this->ad(['name' => 'Second', 'title' => 'Second ad']);

        $this->blade('<x-ad-slot name="home_after_categories" />')
            ->assertSee('First ad')
            ->assertDontSee('Second ad');
    }

    public function test_a_running_campaign_banner_wins_its_place_over_an_ad(): void
    {
        $this->ad();
        $this->campaign();

        $this->blade('<x-ad-slot name="home_after_categories" />')
            ->assertSee('Big sale banner')
            ->assertDontSee('Hello ad');
    }

    public function test_a_campaign_banner_can_be_shown_in_several_places(): void
    {
        $this->campaign(['banner_slots' => ['home_after_hero', 'cart_bottom']]);

        $this->blade('<x-ad-slot name="home_after_hero" />')->assertSee('Big sale banner');
        $this->blade('<x-ad-slot name="cart_bottom" />')->assertSee('Big sale banner');
        $this->blade('<x-ad-slot name="home_after_categories" />')->assertDontSee('Big sale banner');
    }

    public function test_a_campaign_banner_in_another_place_does_not_hide_the_ad(): void
    {
        $this->ad();
        $this->campaign(['banner_slots' => ['home_after_hero']]);

        $this->blade('<x-ad-slot name="home_after_categories" />')->assertSee('Hello ad');
        $this->blade('<x-ad-slot name="home_after_hero" />')->assertSee('Big sale banner');
    }

    public function test_places_used_by_an_ad_or_a_banner_are_reported_as_taken(): void
    {
        $ad = $this->ad(['name' => 'Ad A', 'placements' => ['home_after_hero']]);
        $this->campaign(['name' => 'Sale', 'banner_slots' => ['home_after_categories', 'cart_bottom']]);

        $taken = AdSlots::taken();

        $this->assertStringContainsString('Ad A', $taken['home_after_hero']);
        $this->assertStringContainsString('Sale', $taken['home_after_categories']);
        $this->assertStringContainsString('Sale', $taken['cart_bottom']);
        $this->assertArrayNotHasKey('checkout_above', $taken);

        // The ad being edited does not block its own places
        $this->assertArrayNotHasKey('home_after_hero', AdSlots::taken('ad', $ad->id));
    }

    public function test_ads_and_banners_that_are_off_or_finished_do_not_block_a_place(): void
    {
        $this->ad(['name' => 'Off ad', 'is_active' => false, 'placements' => ['home_after_hero']]);
        $this->ad(['name' => 'Old ad', 'ends_at' => now()->subDay(), 'placements' => ['home_after_categories']]);
        $this->campaign(['name' => 'Ended sale', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay(), 'banner_slots' => ['cart_bottom']]);
        $this->campaign(['name' => 'No banner', 'show_banner' => false, 'banner_slots' => ['checkout_above']]);

        $this->assertSame([], AdSlots::taken());
    }

    public function test_an_ad_reports_the_places_it_shares(): void
    {
        $first = $this->ad(['name' => 'First', 'placements' => ['home_after_categories', 'cart_bottom']]);
        $second = $this->ad(['name' => 'Second', 'placements' => ['home_after_categories']]);

        $this->assertSame(['home_after_categories'], $first->conflicts());
        $this->assertSame(['home_after_categories'], $second->conflicts());

        Ad::query()->whereKey($second->id)->update(['placements' => json_encode(['checkout_below'])]);
        $this->resetCaches();

        $this->assertSame([], $first->fresh()->conflicts());
    }

    public function test_only_safe_links_are_accepted(): void
    {
        $this->assertTrue(Ad::isSafeLink('https://example.com/offer'));
        $this->assertTrue(Ad::isSafeLink('http://example.com'));
        $this->assertTrue(Ad::isSafeLink('/category/rings'));

        $this->assertFalse(Ad::isSafeLink('javascript:alert(1)'));
        $this->assertFalse(Ad::isSafeLink('//evil.example.com'));
        $this->assertFalse(Ad::isSafeLink('data:text/html;base64,AAAA'));
        $this->assertFalse(Ad::isSafeLink('example.com'));
    }

    public function test_the_kind_comes_from_the_stored_file(): void
    {
        $this->assertSame('video', $this->ad(['media' => 'ads/a.mp4'])->kind());
        $this->assertSame('gif', $this->ad(['media' => 'ads/a.gif'])->kind());
        $this->assertSame('image', $this->ad(['media' => 'ads/a.webp'])->kind());
    }
}