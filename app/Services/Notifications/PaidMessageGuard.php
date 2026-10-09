<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\Order;
use App\Models\OutboundMessage;

/**
 * Hard daily cap per paid channel; every send is recorded.
 */
class PaidMessageGuard
{
    /**
     * @param  callable(): ?string  $send  returns the provider message id
     */
    public function send(NotificationChannel $channel, string $driver, string $kind, ?Order $order, int $dailyLimit, callable $send): void
    {
        if (OutboundMessage::sentToday($channel) >= $dailyLimit) {
            throw NotificationFailed::permanent("Daily limit for {$channel->value} reached ({$dailyLimit}).");
        }

        $id = $send();

        OutboundMessage::query()->create([
            'channel' => $channel,
            'driver' => $driver,
            'kind' => $kind,
            'order_id' => $order?->id,
            'provider_message_id' => $id,
        ]);
    }
}
