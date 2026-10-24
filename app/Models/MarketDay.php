<?php

namespace App\Models;

use App\Support\TimeOfDay;
use Carbon\CarbonImmutable;
use Database\Factories\MarketDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One day of an event the stand is open. Shifts, availabilities and costs hang on it.
 *
 * @property int $id
 * @property int $event_id
 * @property CarbonImmutable $date
 * @property string $opens_at
 * @property string $closes_at
 * @property ?string $setup_from
 * @property ?string $teardown_until
 * @property ?CarbonImmutable $settled_at
 * @property-read Event $event
 */
#[Fillable(['event_id', 'date', 'opens_at', 'closes_at', 'setup_from', 'teardown_until', 'settled_at'])]
class MarketDay extends Model
{
    /** @use HasFactory<MarketDayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'settled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<Shift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /** @return HasMany<Availability, $this> */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    /** @return HasMany<EventCost, $this> */
    public function costs(): HasMany
    {
        return $this->hasMany(EventCost::class);
    }

    /** First and last time someone of the team is needed. */
    public function workFrom(): string
    {
        return TimeOfDay::format($this->setup_from ?? $this->opens_at);
    }

    public function workUntil(): string
    {
        return TimeOfDay::format($this->teardown_until ?? $this->closes_at);
    }

    public function label(): string
    {
        return $this->dateLabel('dd, D.M.').' '.$this->event->name;
    }

    /** German date, e.g. dateLabel('dddd') = "Samstag". */
    public function dateLabel(string $format): string
    {
        $date = $this->date->locale('de');
        assert($date instanceof CarbonImmutable);

        return $date->isoFormat($format);
    }
}
