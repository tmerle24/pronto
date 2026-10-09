<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;

/** Pause, order intake or sold-out items changed. */
class EventSettingsChanged
{
    use Dispatchable;

    public function __construct(public readonly Event $event) {}
}
