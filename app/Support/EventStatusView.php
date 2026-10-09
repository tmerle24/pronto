<?php

namespace App\Support;

use App\Models\Event;
use App\Models\MenuItem;

/** Intake state for guests and team: open, pause, sold out. */
final class EventStatusView
{
    /**
     * @return array{open: bool, accepting_orders: bool, paused_until: ?string, paused_minutes: int, sold_out: list<int>}
     */
    public static function make(Event $event): array
    {
        return [
            'open' => $event->isOpenForOrders(),
            'accepting_orders' => $event->accepting_orders,
            'paused_until' => $event->paused_until?->toIso8601String(),
            'paused_minutes' => $event->pauseMinutesLeft(),
            'sold_out' => array_values(MenuItem::query()->where('is_sold_out', true)->pluck('id')->map(fn ($id) => (int) $id)->all()),
        ];
    }
}
