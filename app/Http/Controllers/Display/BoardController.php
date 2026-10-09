<?php

namespace App\Http\Controllers\Display;

use App\Http\Controllers\Controller;
use App\Models\Event;
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
            'event' => ['name' => $event->name, 'token' => $event->display_token],
            'board' => BoardView::make($event),
        ]);
    }

    public function state(string $token): JsonResponse
    {
        return response()->json(['board' => BoardView::make($this->event($token))]);
    }

    private function event(string $token): Event
    {
        return Event::query()->where('display_token', $token)->firstOrFail();
    }
}
