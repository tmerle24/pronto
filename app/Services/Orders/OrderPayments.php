<?php

namespace App\Services\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

class OrderPayments
{
    public function __construct(
        private readonly PaymentGateway $payments,
        private readonly OrderQueue $queue,
    ) {}

    /** Payment provider confirmed the payment (webhook): paid and into the queue. */
    public function confirmOnline(Order $order): Order
    {
        $order = $this->markPaid($order, via: 'stripe');
        if ($order->status === OrderStatus::Cancelled) {
            return $order;
        }

        return $this->queue->release($order);
    }

    public function markPaid(Order $order, ?User $user = null, string $via = 'manual'): Order
    {
        return DB::transaction(function () use ($order, $user, $via): Order {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->payment_status === PaymentStatus::Paid) {
                return $locked;
            }

            $meta = ['via' => $via];
            // paid after the 30 min expiry: the guest paid, so the order comes back
            if ($locked->status === OrderStatus::Cancelled && ! $locked->isQueued()) {
                $locked->status = OrderStatus::New;
                $locked->cancelled_at = null;
                $meta['revived'] = true;
            }

            $from = $locked->payment_status;
            $locked->payment_status = PaymentStatus::Paid;
            $locked->paid_at = now();
            $locked->save();

            $locked->events()->create([
                'type' => OrderEventType::PaymentChanged,
                'from' => $from->value,
                'to' => PaymentStatus::Paid->value,
                'user_id' => $user?->id,
                'meta' => $meta,
            ]);

            return $locked;
        });
    }

    /** Online order not paid within the time limit. */
    public function expire(Order $order): void
    {
        $expired = DB::transaction(function () use ($order): bool {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->payment_status !== PaymentStatus::Pending || $locked->isQueued() || $locked->status === OrderStatus::Cancelled) {
                return false;
            }

            $locked->status = OrderStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->payment_status = PaymentStatus::Failed;
            $locked->save();

            $locked->events()->create([
                'type' => OrderEventType::Expired,
                'from' => OrderStatus::New->value,
                'to' => OrderStatus::Cancelled->value,
            ]);

            return true;
        });

        if ($expired && $order->stripe_payment_intent_id !== null) {
            try {
                $this->payments->cancel($order);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
