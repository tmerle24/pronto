<?php

namespace App\Services\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPaymentChanged;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Storno from the admin. Orders are never deleted. Paid online and Tap to Pay
 * orders are refunded via Stripe; cash and counter card are marked refunded (money is
 * handed back at the stand).
 */
class CancelOrder
{
    public function __construct(private readonly PaymentGateway $payments) {}

    public function __invoke(Order $order, User $user, string $reason, bool $refund = true): Order
    {
        $from = $order->status;
        if (! in_array($from, [OrderStatus::New, OrderStatus::InProgress, OrderStatus::Ready], true)) {
            throw InvalidTransition::between($from, OrderStatus::Cancelled);
        }

        $refundOnline = $refund && $order->payment_status === PaymentStatus::Paid && in_array($order->payment_method, [PaymentMethod::Online, PaymentMethod::TapToPay], true);
        if ($refundOnline) {
            // provider first: if Stripe fails, nothing changes here
            $this->payments->refund($order);
        }

        $cancelled = DB::transaction(function () use ($order, $user, $reason, $refund): Order {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $locked->status;

            $locked->status = OrderStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->cancel_reason = $reason;
            $locked->events()->create([
                'type' => OrderEventType::StatusChanged,
                'from' => $from->value,
                'to' => OrderStatus::Cancelled->value,
                'is_undo' => false,
                'user_id' => $user->id,
                'meta' => ['reason' => $reason],
            ]);

            $paymentFrom = $locked->payment_status;
            if ($refund && $paymentFrom === PaymentStatus::Paid) {
                $locked->payment_status = PaymentStatus::Refunded;
                $locked->refunded_at = now();
            } elseif ($paymentFrom === PaymentStatus::Pending) {
                $locked->payment_status = PaymentStatus::Failed;
            }
            if ($locked->payment_status !== $paymentFrom) {
                $locked->events()->create([
                    'type' => OrderEventType::PaymentChanged,
                    'from' => $paymentFrom->value,
                    'to' => $locked->payment_status->value,
                    'user_id' => $user->id,
                    'meta' => ['via' => $locked->payment_method->value, 'reason' => $reason],
                ]);
            }
            $locked->save();

            return $locked;
        });

        OrderStatusChanged::dispatch($cancelled, $from, OrderStatus::Cancelled);
        OrderPaymentChanged::dispatch($cancelled);

        return $cancelled;
    }
}
