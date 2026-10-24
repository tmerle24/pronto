<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * When a team member could work on a market day; entered by themselves.
 *
 * @property int $id
 * @property int $market_day_id
 * @property int $user_id
 * @property string $available_from
 * @property string $available_until
 * @property ?string $note
 * @property-read User $user
 * @property-read MarketDay $marketDay
 */
#[Fillable(['market_day_id', 'user_id', 'available_from', 'available_until', 'note'])]
class Availability extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MarketDay, $this> */
    public function marketDay(): BelongsTo
    {
        return $this->belongsTo(MarketDay::class);
    }
}
