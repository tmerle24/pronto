<?php

namespace App\Http\Controllers\Team;

use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Orders\OrderLine;
use App\Services\Orders\OrderRejected;
use App\Services\Orders\PlaceOrder;
use App\Services\Payments\TapToPay;
use App\Support\EventStatusView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** "Cassa": team takes the order of a guest in the line. */
class CounterController extends Controller
{
    public function show(TapToPay $tapToPay): Response
    {
        $event = Event::current();

        return Inertia::render('Team/Counter', [
            'event' => $event?->only(['id', 'name', 'slug']),
            'items' => $event === null ? [] : MenuItem::query()->forEditions($event->editions)->get()->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'variant' => $item->variant?->value,
                'icon' => $item->icon,
                'edition' => $item->edition?->value,
                'category' => $item->category->value,
                'price_cents' => $item->price_cents,
                'deposit_cents' => $item->deposit_cents,
                'sold_out' => $item->is_sold_out,
            ])->values(),
            'status' => $event ? EventStatusView::make($event) : null,
            'placed' => session('placed'),
            'tapToPay' => $tapToPay->clientConfig(),
        ]);
    }

    public function store(Request $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.PlaceOrder::MAX_QUANTITY_PER_LINE],
            'lines.*.price_cents' => ['nullable', 'integer', 'min:0', 'max:'.PlaceOrder::MAX_CUSTOM_PRICE_CENTS],
            'payment_method' => ['required', Rule::in([PaymentMethod::Cash->value, PaymentMethod::CounterCard->value])],
            'handed_over' => ['boolean'],
        ]);

        $event = Event::current();
        if ($event === null) {
            return back()->withErrors(['order' => 'Kein aktives Event.']);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $order = $placeOrder(
                $event,
                array_map(fn (array $l) => new OrderLine(
                    (int) $l['id'],
                    (int) $l['quantity'],
                    isset($l['price_cents']) ? (int) $l['price_cents'] : null,
                ), array_values($data['lines'])),
                PaymentMethod::from($data['payment_method']),
                OrderSource::Counter,
                'de',
                $user,
                (bool) ($data['handed_over'] ?? false),
            );
        } catch (OrderRejected $e) {
            return back()->withErrors(['order' => $e->translated()]);
        }

        return back()->with('placed', [
            'number' => $order->pickup_number,
            'handed_over' => $order->pickup_number === null,
            'total_cents' => $order->total_cents,
            'payment_method' => $order->payment_method->value,
        ]);
    }
}
