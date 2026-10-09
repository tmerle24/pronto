<?php

namespace App\Http\Controllers\Team;

use App\Events\EventSettingsChanged;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MenuItem;
use App\Support\EventStatusView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pause, order intake and sold out, one tap from the queue. */
class IntakeController extends Controller
{
    public const PAUSE_OPTIONS = [5, 10, 15, 20, 30];

    public function pause(Request $request): JsonResponse
    {
        $data = $request->validate(['minutes' => ['required', 'integer', Rule::in([0, ...self::PAUSE_OPTIONS])]]);
        $event = $this->event();

        $event->paused_until = $data['minutes'] > 0 ? now()->addMinutes($data['minutes']) : null;
        $event->save();

        return $this->changed($event);
    }

    public function accepting(Request $request): JsonResponse
    {
        $data = $request->validate(['accepting' => ['required', 'boolean']]);
        $event = $this->event();

        $event->accepting_orders = (bool) $data['accepting'];
        $event->save();

        return $this->changed($event);
    }

    public function soldOut(Request $request, MenuItem $item): JsonResponse
    {
        $data = $request->validate(['sold_out' => ['required', 'boolean']]);

        $item->is_sold_out = (bool) $data['sold_out'];
        $item->save();

        return $this->changed($this->event());
    }

    private function event(): Event
    {
        return Event::current() ?? abort(409, 'Kein aktives Event.');
    }

    private function changed(Event $event): JsonResponse
    {
        EventSettingsChanged::dispatch($event);

        return response()->json(['status' => EventStatusView::make($event)]);
    }
}
