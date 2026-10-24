<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cost of an event: for one market day, or shared evenly by all its days (market_day_id null).
 *
 * @property int $id
 * @property int $event_id
 * @property ?int $market_day_id
 * @property string $label
 * @property int $amount_cents
 */
#[Fillable(['event_id', 'market_day_id', 'label', 'amount_cents'])]
class EventCost extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<MarketDay, $this> */
    public function marketDay(): BelongsTo
    {
        return $this->belongsTo(MarketDay::class);
    }
}
