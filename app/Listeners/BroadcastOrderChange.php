<?php

namespace App\Listeners;

use App\Broadcasting\BoardUpdated;
use App\Broadcasting\GuestOrderUpdated;
use App\Broadcasting\TeamOrderUpdated;
use App\Events\OrderPaymentChanged;
use App\Events\OrderQueued;
use App\Events\OrderStatusChanged;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

/**
 * Pushes changes to queue tablets, display and status page. A Reverb outage
 * must never block the kitchen, clients fall back to polling.
 */
class BroadcastOrderChange implements ShouldHandleEventsAfterCommit
{
    public function handle(OrderQueued|OrderStatusChanged|OrderPaymentChanged $event): void
    {
        $order = $event->order;

        foreach ([new TeamOrderUpdated($order), new GuestOrderUpdated($order), new BoardUpdated($order->event)] as $message) {
            try {
                broadcast($message);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
