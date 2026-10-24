<?php

namespace App\View\Components;

use App\Models\Ad;
use App\Models\Campaign;
use App\Support\AdSlots;
use App\Support\CampaignPricing;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-ad-slot name="home_after_categories" />
 * Shows what belongs in that place: a running campaign banner first, else the best ad, else nothing.
 */
class AdSlot extends Component
{
    public ?Campaign $campaign = null;

    public ?Ad $ad = null;

    public function __construct(public string $name)
    {
        if (! AdSlots::isOn($name)) {
            return;
        }

        $this->campaign = CampaignPricing::bannerFor($name);

        if (! $this->campaign) {
            $this->ad = Ad::liveFor($name);
        }
    }

    public function shouldRender(): bool
    {
        return $this->campaign !== null || $this->ad !== null;
    }

    public function render(): View
    {
        return view('components.ad-slot');
    }
}