<?php

namespace App\Services\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\CardTerminal;
use App\Services\Payments\TerminalPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Counter order paid by a tap on the team phone. The order exists (pending,
 * not queued) before the tap; amount always from the order total. Every step
 * is safe to repeat; the Stripe webhook completes it if the app never reports back.
 */
class TapToPayCheckout
{
    public function __construct(
        private readonly PlaceOrder $placeOrder,
        private readonly OrderPayments $payments,
        private readonly OrderQueue $queue,
        private readonly CancelOrder $cancelOrder,
        private readonly CardTerminal $terminal,
    ) {}

    /**
     * @param  list<OrderLine>  $lines
     */
    public function start(Event $event, array $lines, User $user, bool $handedOver = false): Order
    {
        return ($this->placeOrder)($event, $lines, PaymentMethod::TapToPay, OrderSource::Counter, 'de', $user, $handedOver);
    }

    public function clientSecret(Order $order): string
    {
        return $this->terminal->clientSecret($order);
    }

    /**
     * App reports a successful tap: verify with Stripe, then paid and queued.
     *
     * @throws TapToPayIncomplete
     */
    public function complete(Order $order, User $user): Order
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return $this->queue->finishPaid($order, $user);
        }

        $payment = $this->terminal->status($order);
        if ($payment->status === TerminalPayment::REQUIRES_CAPTURE) {
            $payment = $this->terminal->capture($order);
        }
        if (! $payment->succeeded()) {
            throw new TapToPayIncomplete($payment->status);
        }
        if ($payment->amountCents !== $order->total_cents || $payment->currency !== 'eur') {
            // never expected: amount is set by us; keep the order out of the queue
            Log::error('tap to pay amount mismatch', ['order' => $order->id, 'charged' => $payment->amountCents, 'total' => $order->total_cents]);

            throw new TapToPayIncomplete('amount_mismatch');
        }

        return $this->queue->finishPaid($this->payments->markPaid($order, $user, 'tap_to_pay'), $user);
    }

    /**
     * Guest pays cash after all. If the tap went through meanwhile, the order is completed as card.
     */
    public function switchToCash(Order $order, User $user): Order
    {
        if ($order->payment_status === PaymentStatus::Paid || ! $this->terminal->cancel($order)) {
            return $this->complete($order, $user);
        }

        $switched = DB::transaction(function () use ($order, $user): Order {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->payment_method !== PaymentMethod::TapToPay) {
                return $locked;
            }

            $locked->payment_method = PaymentMethod::Cash;
            $locked->save();
            $locked->events()->create([
                'type' => OrderEventType::PaymentMethodChanged,
                'from' => PaymentMethod::TapToPay->value,
                'to' => PaymentMethod::Cash->value,
                'user_id' => $user->id,
                'meta' => ['payment' => $locked->stripe_payment_intent_id],
            ]);

            return $locked;
        });

        return $this->queue->finishPaid($this->payments->markPaid($switched, $user, 'counter_cash'), $user);
    }

    /**
     * Team cancels before anything was charged. If the tap went through meanwhile, the order is completed.
     */
    public function abort(Order $order, User $user): Order
    {
        if ($order->payment_status === PaymentStatus::Paid || ! $this->terminal->cancel($order)) {
            return $this->complete($order, $user);
        }

        if ($order->status === OrderStatus::Cancelled) {
            return $order;
        }

        return ($this->cancelOrder)($order, $user, 'Tap to Pay abgebrochen', refund: false);
    }
}
