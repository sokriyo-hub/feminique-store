<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    // The orders table has many columns: use the whole width so nobody has to scroll sideways
    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }
}