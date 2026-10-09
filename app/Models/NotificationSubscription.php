<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property NotificationChannel $channel
 * @property ?string $target
 * @property NotificationStatus $status
 * @property int $attempts
 * @property ?string $last_error
 * @property ?CarbonImmutable $sent_at
 * @property ?CarbonImmutable $purged_at
 * @property CarbonImmutable $created_at
 * @property-read Order $order
 */
#[Fillable(['channel', 'target', 'status'])]
class NotificationSubscription extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'target' => 'encrypted',
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'immutable_datetime',
            'purged_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
