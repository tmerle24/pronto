<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Events\CustomerNotified;
use App\Models\NotificationSubscription;
use App\Services\Notifications\NotificationFailed;
use App\Services\Notifications\Notifiers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * "Pronto!" to one channel of one guest. Bounded retries; skipped if the
 * order is no longer ready (undo on the tablet, already picked up).
 */
class SendReadyNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public readonly int $subscriptionId) {}

    public function handle(Notifiers $notifiers): void
    {
        $subscription = NotificationSubscription::query()->with('order')->find($this->subscriptionId);
        if ($subscription === null || $subscription->status !== NotificationStatus::Active || $subscription->target === null) {
            return;
        }

        $order = $subscription->order;
        if ($order->status !== OrderStatus::Ready) {
            return;
        }

        $subscription->attempts++;
        try {
            $notifiers->for($subscription->channel)->sendReady($subscription);
        } catch (NotificationFailed $e) {
            $subscription->last_error = mb_substr($e->getMessage(), 0, 250);
            if ($e->permanent) {
                $subscription->status = NotificationStatus::Failed;
                $subscription->save();

                return;
            }
            $subscription->save();

            throw $e;
        }

        $subscription->status = NotificationStatus::Sent;
        $subscription->sent_at = now();
        $subscription->last_error = null;
        $subscription->save();

        if ($order->customer_notified_at === null) {
            $order->customer_notified_at = now();
            $order->save();
        }
        $order->events()->create([
            'type' => OrderEventType::CustomerNotified,
            'to' => $subscription->channel->value,
        ]);

        CustomerNotified::dispatch($order);
    }

    public function failed(?Throwable $e): void
    {
        NotificationSubscription::query()->whereKey($this->subscriptionId)->update([
            'status' => NotificationStatus::Failed,
            'last_error' => mb_substr((string) $e?->getMessage(), 0, 250),
        ]);
    }
}
