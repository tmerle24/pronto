<?php

namespace App\Broadcasting;

use App\Models\Order;
use App\Support\GuestOrderView;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Public channel, the unguessable order token is the secret. */
class GuestOrderUpdated implements ShouldBroadcastNow
{
    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): Channel
    {
        return new Channel('order.'.$this->order->public_token);
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['order' => GuestOrderView::make($this->order->loadMissing('items'))];
    }
}
