<?php

namespace App\Models;

use App\Enums\MenuEdition;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property ?string $location
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $is_active
 * @property Collection<int, MenuEdition> $editions
 * @property bool $accepting_orders
 * @property ?CarbonImmutable $paused_until
 * @property ?string $pause_message
 * @property bool $cash_allowed
 * @property int $oven_slots
 * @property int $minutes_per_bake
 * @property int $buffer_minutes
 * @property string $display_token
 * @property int $last_pickup_number
 */
#[Fillable([
    'name', 'slug', 'location', 'starts_on', 'ends_on', 'is_active', 'editions',
    'accepting_orders', 'paused_until', 'pause_message', 'cash_allowed',
    'oven_slots', 'minutes_per_bake', 'buffer_minutes',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            $event->display_token ??= Str::random(40);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'is_active' => 'boolean',
            'editions' => AsEnumCollection::of(MenuEdition::class),
            'accepting_orders' => 'boolean',
            'paused_until' => 'immutable_datetime',
            'cash_allowed' => 'boolean',
            'oven_slots' => 'integer',
            'minutes_per_bake' => 'integer',
            'buffer_minutes' => 'integer',
            'last_pickup_number' => 'integer',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->orderByDesc('starts_on')->first();
    }

    public function hasEdition(MenuEdition $edition): bool
    {
        return $this->editions->contains($edition);
    }

    public function isPaused(): bool
    {
        return $this->paused_until !== null && $this->paused_until->isFuture();
    }

    public function isOpenForOrders(): bool
    {
        return $this->is_active && $this->accepting_orders && ! $this->isPaused();
    }

    public function pauseMinutesLeft(): int
    {
        if (! $this->isPaused()) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($this->paused_until) / 60);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
