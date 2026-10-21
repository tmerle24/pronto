<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\InvalidTransition;
use App\Services\Orders\OrderWorkflow;
use App\Services\Orders\TransitionResult;
use App\Services\WaitTime\EventWaitTimes;
use App\Support\EventStatusView;
use App\Support\TeamOrderView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    public function show(): Response
    {
        $event = Event::current();

        return Inertia::render('Team/Queue', [
            'event' => $event?->only(['id', 'name', 'slug']),
            'orders' => $event ? $this->orders($event) : [],
            'wait' => $event ? (new EventWaitTimes($event))->general()->toArray() : null,
            'status' => $event ? EventStatusView::make($event) : null,
            'items' => $event === null ? [] : MenuItem::query()->forEditions($event->editions)->get(['id', 'name', 'variant', 'is_sold_out'])
                ->map(fn (MenuItem $item) => ['id' => $item->id, 'name' => $item->name, 'variant' => $item->variant?->value])
                ->values(),
            'pause_options' => IntakeController::PAUSE_OPTIONS,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Resync after reconnect or offline phase. */
    public function state(): JsonResponse
    {
        $event = Event::current();

        return response()->json([
            'orders' => $event ? $this->orders($event) : [],
            'wait' => $event ? (new EventWaitTimes($event))->general()->toArray() : null,
            'status' => $event ? EventStatusView::make($event) : null,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function advance(Request $request, Order $order, OrderWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid']]);

        return $this->respond(fn () => $workflow->advance($order, $this->user($request), $data['request_id']), $order);
    }

    public function undo(Request $request, Order $order, OrderWorkflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'undo_of' => ['required', 'uuid'],
        ]);

        return $this->respond(fn () => $workflow->undo($order, $data['undo_of'], $this->user($request), $data['request_id']), $order);
    }

    /**
     * Conflicts (another tablet was faster) answer 409 with the current card,
     * so the tablet can show the real state.
     *
     * @param  callable(): TransitionResult  $change
     */
    private function respond(callable $change, Order $order): JsonResponse
    {
        try {
            $result = $change();
        } catch (InvalidTransition $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'order' => TeamOrderView::make($order->refresh()->load('items')),
            ], 409);
        }

        return response()->json([
            'duplicate' => $result->duplicate,
            'order' => TeamOrderView::make($result->order->load('items')),
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orders(Event $event): array
    {
        return array_values(Order::query()
            ->where('event_id', $event->id)
            ->inQueue()
            ->with('items.menuItem')
            ->get()
            ->map(fn (Order $order) => TeamOrderView::make($order))
            ->all());
    }
}
