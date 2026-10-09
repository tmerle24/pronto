<?php

namespace App\Listeners;

use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Jobs\SendReadyNotification;
use App\Models\NotificationSubscription;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class NotifyCustomerWhenReady implements ShouldHandleEventsAfterCommit
{
    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Ready || $event->isUndo) {
            return;
        }

        self::dispatchFor(array_values($event->order->notificationSubscriptions()->where('status', NotificationStatus::Active)->get()->all()));
    }

    /**
     * @param  list<NotificationSubscription>  $subscriptions
     */
    public static function dispatchFor(array $subscriptions, bool $immediately = false): void
    {
        $delay = $immediately ? 0 : (int) config('pronto.notifications.delay_seconds');

        foreach ($subscriptions as $subscription) {
            SendReadyNotification::dispatch($subscription->id)->delay($delay);
        }
    }
}
