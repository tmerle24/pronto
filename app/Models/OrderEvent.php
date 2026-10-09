<?php

namespace App\Models;

use App\Enums\OrderEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only.
 *
 * @property int $id
 * @property int $order_id
 * @property OrderEventType $type
 * @property ?string $from
 * @property ?string $to
 * @property bool $is_undo
 * @property ?int $user_id
 * @property ?string $client_request_id
 * @property ?array<string, mixed> $meta
 * @property CarbonImmutable $created_at
 */
#[Fillable(['type', 'from', 'to', 'is_undo', 'user_id', 'client_request_id', 'meta'])]
class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Order events are append-only.'));
        static::deleting(fn () => throw new LogicException('Order events are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderEventType::class,
            'is_undo' => 'boolean',
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
