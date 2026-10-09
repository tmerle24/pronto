<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Services\Reports\DailyReport as Report;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class DailyReport extends Page
{
    protected string $view = 'filament.pages.daily-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Tagesübersicht';

    protected static ?string $title = 'Tagesübersicht';

    protected static ?int $navigationSort = 5;

    public ?int $eventId = null;

    // empty = whole event
    public ?string $day = null;

    public function mount(): void
    {
        $event = Event::current() ?? Event::query()->orderByDesc('starts_on')->first();
        $this->eventId = $event?->id;
        $this->day = today()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $event = $this->eventId ? Event::query()->find($this->eventId) : null;
        $day = $this->day ? CarbonImmutable::parse($this->day) : null;

        return [
            'events' => Event::query()->orderByDesc('starts_on')->pluck('name', 'id'),
            'report' => $event ? (new Report($event, $day))->summary() : null,
            'csvUrl' => $event ? route('admin.report.csv', array_filter(['event' => $event->id, 'day' => $this->day])) : null,
        ];
    }
}
