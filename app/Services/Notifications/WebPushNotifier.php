<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\NotificationSubscription;

class WebPushNotifier implements CustomerNotifier
{
    public function __construct(private readonly PushSender $sender) {}

    public function channel(): NotificationChannel
    {
        return NotificationChannel::WebPush;
    }

    public function sendReady(NotificationSubscription $subscription): void
    {
        $order = $subscription->order;
        /** @var array<string, mixed>|null $target */
        $target = json_decode((string) $subscription->target, true);
        if (! is_array($target) || ! isset($target['endpoint'])) {
            throw NotificationFailed::permanent('Invalid push subscription.');
        }

        $this->sender->send($target, (string) json_encode([
            'title' => 'Pronto! Nr. '.$order->pickup_number,
            'body' => Messages::ready($order),
            'url' => route('guest.order', $order),
            'tag' => 'order-'.$order->id,
        ]));
    }
}
