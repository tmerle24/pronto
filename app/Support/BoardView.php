<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;

/** Pickup display: numbers only, no personal data, no order contents. */
final class BoardView
{
    /**
     * @return array{in_oven: list<int>, ready: list<int>}
     */
    public static function make(Event $event): array
    {
        $orders = Order::query()->where('event_id', $event->id)->inQueue()->get(['pickup_number', 'status']);

        $numbers = fn (array $statuses) => array_values($orders
            ->filter(fn (Order $o) => in_array($o->status, $statuses, true))
            ->map(fn (Order $o) => (int) $o->pickup_number)
            ->all());

        return [
            'in_oven' => $numbers([OrderStatus::New, OrderStatus::InProgress]),
            'ready' => $numbers([OrderStatus::Ready]),
        ];
    }
}
