<?php

namespace App\Http\Controllers\Guest;

use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Orders\OrderLine;
use App\Services\Orders\OrderRejected;
use App\Services\Orders\PlaceOrder;
use App\Services\Payments\OnlinePayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function store(Request $request, Event $event, PlaceOrder $placeOrder, OnlinePayments $online): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.PlaceOrder::MAX_QUANTITY_PER_LINE],
            'payment_method' => ['required', Rule::in([PaymentMethod::Online->value, PaymentMethod::Cash->value])],
        ]);

        $method = PaymentMethod::from($data['payment_method']);
        if ($method === PaymentMethod::Online && ! $online->enabled()) {
            return back()->withErrors(['order' => __('Online-Zahlung ist gerade nicht möglich.')]);
        }

        try {
            $order = $placeOrder(
                $event,
                array_map(fn (array $l) => new OrderLine((int) $l['id'], (int) $l['quantity']), array_values($data['lines'])),
                $method,
                OrderSource::Qr,
                app()->getLocale(),
            );
        } catch (OrderRejected $e) {
            return back()->withErrors(['order' => $e->translated()]);
        }

        return $method === PaymentMethod::Online
            ? redirect()->route('guest.order.pay', $order)
            : redirect()->route('guest.order', $order);
    }
}
