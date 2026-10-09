<?php

namespace App\Http\Controllers\Display;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\WaitTime\EventWaitTimes;
use App\Support\BoardView;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function show(string $token): Response
    {
        $event = $this->event($token);

        return Inertia::render('Display/Board', [
            'event' => ['id' => $event->id, 'name' => $event->name, 'token' => $event->display_token],
            'board' => BoardView::make($event),
            'wait' => (new EventWaitTimes($event))->general()->toArray(),
        ]);
    }

    public function state(string $token): JsonResponse
    {
        $event = $this->event($token);

        return response()->json([
            'board' => BoardView::make($event),
            'wait' => (new EventWaitTimes($event))->general()->toArray(),
        ]);
    }

    private function event(string $token): Event
    {
        return Event::query()->where('display_token', $token)->firstOrFail();
    }
}
