<?php

namespace App\Http\Controllers\Team;

use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderLine;
use App\Services\Orders\OrderRejected;
use App\Services\Orders\PlaceOrder;
use App\Services\Orders\TapToPayCheckout;
use App\Services\Orders\TapToPayIncomplete;
use App\Services\Payments\CardTerminal;
use App\Services\Payments\TapToPay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON endpoints for the counter app; the native Stripe SDK does the tap in between. */
class TapToPayController extends Controller
{
    public function __construct(private readonly TapToPay $tapToPay) {}

    public function connectionToken(CardTerminal $terminal): JsonResponse
    {
        abort_unless($this->tapToPay->enabled(), 404);

        return response()->json(['secret' => $terminal->connectionToken()]);
    }

    public function store(Request $request, TapToPayCheckout $checkout): JsonResponse
    {
        abort_unless($this->tapToPay->enabled(), 404);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.PlaceOrder::MAX_QUANTITY_PER_LINE],
        ]);

        $event = Event::current();
        if ($event === null) {
            return response()->json(['message' => 'Kein aktives Event.'], 422);
        }

        try {
            $order = $checkout->start(
                $event,
                array_map(fn (array $l) => new OrderLine((int) $l['id'], (int) $l['quantity']), array_values($data['lines'])),
                $this->user($request),
            );
        } catch (OrderRejected $e) {
            return response()->json(['message' => $e->translated()], 422);
        }

        return response()->json([
            'order' => $order->id,
            'total_cents' => $order->total_cents,
            'client_secret' => $checkout->clientSecret($order),
        ]);
    }

    public function complete(Request $request, Order $order, TapToPayCheckout $checkout): JsonResponse
    {
        $this->guard($order);

        try {
            $order = $checkout->complete($order, $this->user($request));
        } catch (TapToPayIncomplete $e) {
            return response()->json(['message' => 'Zahlung nicht abgeschlossen.', 'status' => $e->paymentStatus], 409);
        }

        return $this->placed($order);
    }

    public function cash(Request $request, Order $order, TapToPayCheckout $checkout): JsonResponse
    {
        $this->guard($order);

        return $this->placed($checkout->switchToCash($order, $this->user($request)));
    }

    public function cancel(Request $request, Order $order, TapToPayCheckout $checkout): JsonResponse
    {
        $this->guard($order);

        $order = $checkout->abort($order, $this->user($request));

        return $order->isQueued() ? $this->placed($order) : response()->json(['cancelled' => true]);
    }

    private function guard(Order $order): void
    {
        // cash stays allowed: a repeated "Bar" tap after the switch must not fail
        abort_unless(
            $order->source === OrderSource::Counter && in_array($order->payment_method, [PaymentMethod::TapToPay, PaymentMethod::Cash], true),
            404,
        );
    }

    private function placed(Order $order): JsonResponse
    {
        return response()->json(['placed' => [
            'number' => $order->pickup_number,
            'total_cents' => $order->total_cents,
            'payment_method' => $order->payment_method->value,
        ]]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
