<?php

namespace App\Services\Orders;

use App\Enums\OrderEventType;
use App\Events\OrderQueued;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Releases orders into the kitchen queue and hands out pickup numbers.
 */
class OrderQueue
{
    public function release(Order $order): Order
    {
        $released = DB::transaction(function () use ($order): ?Order {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->isQueued()) {
                return null;
            }

            /** @var Event $event */
            $event = Event::query()->lockForUpdate()->findOrFail($locked->event_id);
            $event->last_pickup_number++;
            $event->save();

            $locked->pickup_number = $event->last_pickup_number;
            $locked->queued_at = now();
            $locked->save();

            $locked->events()->create([
                'type' => OrderEventType::Queued,
                'to' => (string) $locked->pickup_number,
            ]);

            return $locked;
        });

        if ($released === null) {
            return $order->refresh();
        }

        OrderQueued::dispatch($released);

        return $released;
    }
}
