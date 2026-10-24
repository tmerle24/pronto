<?php

namespace App\Filament\Resources\MarketDays\Pages;

use App\Filament\Resources\MarketDays\MarketDayResource;
use App\Models\MarketDay;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditMarketDay extends EditRecord
{
    protected static string $resource = MarketDayResource::class;

    public function getTitle(): string
    {
        /** @var MarketDay $day */
        $day = $this->getRecord();

        return 'Planen: '.$day->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settle')->label('Abrechnung')->icon('heroicon-o-banknotes')
                ->url(fn () => MarketDayResource::getUrl('settle', ['record' => $this->getRecord()])),
        ];
    }
}
