<?php

namespace App\Filament\Resources\MarketDays\Pages;

use App\Filament\Resources\MarketDays\MarketDayResource;
use Filament\Resources\Pages\ListRecords;

class ListMarketDays extends ListRecords
{
    protected static string $resource = MarketDayResource::class;

    public function getSubheading(): ?string
    {
        return 'Markttage legst du beim Event an (Events, Event öffnen, "Markttage").';
    }
}
