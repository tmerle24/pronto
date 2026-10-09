<?php

namespace App\Broadcasting;

use App\Models\Event;
use App\Services\WaitTime\EventWaitTimes;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * One message for all open status pages, queue and display: general wait
 * time plus per pickup number. No personal data.
 */
class WaitTimesUpdated implements ShouldBroadcastNow
{
    public function __construct(public readonly Event $event) {}

    public function broadcastOn(): Channel
    {
        return new Channel('wait.'.$this->event->id);
    }

    public function broadcastAs(): string
    {
        return 'wait.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $times = new EventWaitTimes($this->event);

        return ['general' => $times->general()->toArray(), 'orders' => $times->allOpen()];
    }
}
