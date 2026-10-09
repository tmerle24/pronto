<?php

namespace App\Broadcasting;

use App\Models\Order;
use App\Support\TeamOrderView;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TeamOrderUpdated implements ShouldBroadcastNow
{
    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('team.'.$this->order->event_id);
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['order' => TeamOrderView::make($this->order->loadMissing('items'))];
    }
}
