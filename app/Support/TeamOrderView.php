<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;

/** Order card on the queue tablet. */
final class TeamOrderView
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Order $order): array
    {
        $order->loadMissing('items.menuItem');

        return [
            'id' => $order->id,
            'number' => $order->pickup_number,
            'status' => $order->status->value,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            'cash_open' => $order->isCashOpen(),
            'source' => $order->source->value,
            'total_cents' => $order->total_cents,
            'queued_at' => $order->queued_at?->toIso8601String(),
            'started_at' => $order->started_at?->toIso8601String(),
            'ready_at' => $order->ready_at?->toIso8601String(),
            'customer_notified_at' => $order->customer_notified_at?->toIso8601String(),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'quantity' => $item->quantity,
                'name' => $item->name,
                'variant' => $item->variant?->value,
                'icon' => $item->menuItem?->icon,
            ])->all(),
        ];
    }
}
