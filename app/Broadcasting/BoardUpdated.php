<?php

namespace App\Broadcasting;

use App\Models\Event;
use App\Support\BoardView;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Full board snapshot each time, so a missed message heals itself. */
class BoardUpdated implements ShouldBroadcastNow
{
    public function __construct(public readonly Event $event) {}

    public function broadcastOn(): Channel
    {
        return new Channel('board.'.$this->event->display_token);
    }

    public function broadcastAs(): string
    {
        return 'board.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['board' => BoardView::make($this->event)];
    }
}
