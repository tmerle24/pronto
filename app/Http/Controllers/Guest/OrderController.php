<?php

namespace App\Http\Controllers\Guest;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderPayments;
use App\Services\Payments\OnlinePayments;
use App\Services\Payments\PaymentGateway;
use App\Services\WaitTime\EventWaitTimes;
use App\Support\GuestOrderView;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function show(Order $order): Response
    {
        $order->load('items');

        return Inertia::render('Guest/Status', [
            'order' => GuestOrderView::make($order),
            'event' => $order->event->only(['id', 'name', 'slug']),
            'wait' => (new EventWaitTimes($order->event))->forOrder($order),
        ]);
    }

    public function pay(Order $order, PaymentGateway $gateway, OnlinePayments $online): Response|RedirectResponse
    {
        if ($order->payment_method !== PaymentMethod::Online || $order->payment_status !== PaymentStatus::Pending || $order->isQueued()) {
            return redirect()->route('guest.order', $order);
        }

        $order->load('items');

        return Inertia::render('Guest/Pay', [
            'order' => GuestOrderView::make($order),
            'event' => $order->event->only(['name', 'slug']),
            'stripe' => $online->simulated() ? null : [
                'key' => config('services.stripe.key'),
                'client_secret' => $gateway->clientSecret($order),
            ],
            'return_url' => route('guest.order', $order),
        ]);
    }

    /** Local demo without Stripe keys. */
    public function simulatePayment(Order $order, OnlinePayments $online, OrderPayments $payments): RedirectResponse
    {
        abort_unless($online->simulated(), 404);
        $payments->confirmOnline($order);

        return redirect()->route('guest.order', $order);
    }
}
