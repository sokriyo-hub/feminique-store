@if ($campaign)
    <x-campaign-banner :campaign="$campaign" />
@elseif ($ad)
    <x-ad-banner :ad="$ad" />
@endif