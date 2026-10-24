<?php

namespace App\Models;

use App\Support\TimeOfDay;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Planned work of a team member on a market day; the admin sets the real times after the day.
 *
 * @property int $id
 * @property int $market_day_id
 * @property int $user_id
 * @property string $starts_at
 * @property string $ends_at
 * @property ?string $task
 * @property-read User $user
 * @property-read MarketDay $marketDay
 */
#[Fillable(['market_day_id', 'user_id', 'starts_at', 'ends_at', 'task'])]
class Shift extends Model
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

    public function hours(): float
    {
        return TimeOfDay::hours($this->starts_at, $this->ends_at);
    }
}
