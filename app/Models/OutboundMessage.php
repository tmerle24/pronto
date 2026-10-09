<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property NotificationChannel $channel
 * @property string $driver
 * @property string $kind
 * @property ?int $order_id
 * @property ?string $provider_message_id
 * @property CarbonImmutable $created_at
 */
#[Fillable(['channel', 'driver', 'kind', 'order_id', 'provider_message_id'])]
class OutboundMessage extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    public static function sentToday(NotificationChannel $channel): int
    {
        return static::query()->where('channel', $channel)->where('created_at', '>=', today())->count();
    }
}
