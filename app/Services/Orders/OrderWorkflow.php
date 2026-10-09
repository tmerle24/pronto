<?php

namespace App\Services\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaymentChanged;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use App\Support\Counter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Kitchen status transitions. Every change is logged in order_events and
 * idempotent per client request id (queue tablet replays after offline).
 */
class OrderWorkflow
{
    /** One-tap step; on pickup of an open cash order also marks it paid. */
    public function advance(Order $order, ?User $user = null, ?string $clientRequestId = null): TransitionResult
    {
        return $this->run($order, $clientRequestId, function (Order $locked) use ($user, $clientRequestId): OrderEvent {
            $next = $locked->status->next() ?? throw InvalidTransition::between($locked->status, $locked->status);

            return $this->apply($locked, $next, $user, $clientRequestId, settleCash: true);
        });
    }

    public function transition(Order $order, OrderStatus $to, ?User $user = null, ?string $clientRequestId = null): TransitionResult
    {
        return $this->run($order, $clientRequestId, fn (Order $locked): OrderEvent => $this->apply($locked, $to, $user, $clientRequestId));
    }

    /**
     * Reverts the change made by $originalRequestId (or the latest one) if the
     * order is still in the state that change produced.
     */
    public function undo(Order $order, ?string $originalRequestId = null, ?User $user = null, ?string $clientRequestId = null): TransitionResult
    {
        return $this->run($order, $clientRequestId, function (Order $locked) use ($originalRequestId, $user, $clientRequestId): OrderEvent {
            $original = $this->lastUndoableChange($locked, $originalRequestId);
            if ($original === null || $original->to !== $locked->status->value || $original->from === null) {
                throw InvalidTransition::nothingToUndo();
            }

            $from = $locked->status;
            $to = OrderStatus::from($original->from);

            if (($column = $from->timestampColumn()) !== null) {
                $locked->{$column} = null;
            }
            $locked->status = $to;

            $meta = null;
            $paymentFrom = $original->meta['payment_from'] ?? null;
            if (is_string($paymentFrom)) {
                $meta = ['payment_from' => $locked->payment_status->value];
                $locked->payment_status = PaymentStatus::from($paymentFrom);
                $locked->paid_at = null;
            }
            $locked->save();

            return $locked->events()->create([
                'type' => OrderEventType::StatusChanged,
                'from' => $from->value,
                'to' => $to->value,
                'is_undo' => true,
                'user_id' => $user?->id,
                'client_request_id' => $clientRequestId,
                'meta' => ($meta ?? []) + ['undoes' => $original->id],
            ]);
        });
    }

    /**
     * @param  callable(Order): OrderEvent  $change
     */
    private function run(Order $order, ?string $clientRequestId, callable $change): TransitionResult
    {
        if ($clientRequestId !== null && $this->alreadyProcessed($clientRequestId)) {
            return new TransitionResult($order->refresh(), null, duplicate: true);
        }

        try {
            [$locked, $event, $from] = DB::transaction(function () use ($order, $change): array {
                /** @var Order $locked */
                $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                if (! $locked->isQueued()) {
                    throw InvalidTransition::notQueued();
                }
                $from = $locked->status;

                return [$locked, $change($locked), $from];
            });
        } catch (UniqueConstraintViolationException) {
            // concurrent replay of the same request
            return new TransitionResult($order->refresh(), null, duplicate: true);
        }

        OrderStatusChanged::dispatch($locked, $from, $locked->status, $event->is_undo);
        if (array_key_exists('payment_from', $event->meta ?? [])) {
            OrderPaymentChanged::dispatch($locked);
        }

        return new TransitionResult($locked, $event);
    }

    private function apply(Order $order, OrderStatus $to, ?User $user, ?string $clientRequestId, bool $settleCash = false): OrderEvent
    {
        $from = $order->status;
        if (! $from->canTransitionTo($to)) {
            throw InvalidTransition::between($from, $to);
        }

        $order->status = $to;
        if (($column = $to->timestampColumn()) !== null) {
            $order->{$column} = now();
        }

        $meta = null;
        if ($settleCash && $to === OrderStatus::PickedUp && $order->isCashOpen()) {
            $meta = ['payment_from' => $order->payment_status->value];
            $order->payment_status = PaymentStatus::Paid;
            $order->paid_at = now();
            $order->receipt_number ??= sprintf('%d-%06d', now()->year, Counter::next('receipt-'.now()->year));
        }
        $order->save();

        return $order->events()->create([
            'type' => OrderEventType::StatusChanged,
            'from' => $from->value,
            'to' => $to->value,
            'is_undo' => false,
            'user_id' => $user?->id,
            'client_request_id' => $clientRequestId,
            'meta' => $meta,
        ]);
    }

    /** Only the most recent forward change can be undone. */
    private function lastUndoableChange(Order $order, ?string $originalRequestId): ?OrderEvent
    {
        $latest = OrderEvent::query()
            ->where('order_id', $order->id)
            ->where('type', OrderEventType::StatusChanged)
            ->where('is_undo', false)
            ->orderByDesc('id')
            ->first();

        if ($latest === null || ($originalRequestId !== null && $latest->client_request_id !== $originalRequestId)) {
            return null;
        }

        return $latest;
    }

    private function alreadyProcessed(string $clientRequestId): bool
    {
        return OrderEvent::query()->where('client_request_id', $clientRequestId)->exists();
    }
}
