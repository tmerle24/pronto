<?php

namespace App\Filament\Resources\MarketDays\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\MarketDays\MarketDayResource;
use App\Models\MarketDay;
use App\Services\Staffing\DaySettlement;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/** Profit of the day and everyone's share by hours; "Abrechnen" fixes it for the team. */
class SettleMarketDay extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MarketDayResource::class;

    protected string $view = 'filament.pages.settle-market-day';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Abrechnung: '.$this->day()->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('plan')->label('Einsätze bearbeiten')->color('gray')->icon('heroicon-o-user-group')
                ->url(fn () => MarketDayResource::getUrl('edit', ['record' => $this->day()])),
            Action::make('costs')->label('Kosten bearbeiten')->color('gray')->icon('heroicon-o-receipt-percent')
                ->url(fn () => EventResource::getUrl('edit', ['record' => $this->day()->event_id])),
            Action::make('settle')->label('Abrechnen')->icon('heroicon-o-check')
                ->visible(fn () => $this->day()->settled_at === null)
                ->requiresConfirmation()
                ->modalDescription('Danach sieht jeder im Team seinen Betrag als "abgerechnet". Du kannst es wieder öffnen.')
                ->action(fn () => $this->day()->update(['settled_at' => now()])),
            Action::make('reopen')->label('Wieder öffnen')->color('gray')
                ->visible(fn () => $this->day()->settled_at !== null)
                ->action(fn () => $this->day()->update(['settled_at' => null])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['s' => (new DaySettlement($this->day()))->summary(), 'settled' => $this->day()->settled_at];
    }

    private function day(): MarketDay
    {
        /** @var MarketDay */
        return $this->getRecord();
    }
}
