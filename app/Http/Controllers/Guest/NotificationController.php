<?php

namespace App\Http\Controllers\Guest;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Listeners\NotifyCustomerWhenReady;
use App\Models\Order;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const MAX_PER_CHANNEL = 3;

    public function push(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        abort_unless($this->accepts($order, NotificationChannel::WebPush), 422);

        $this->subscribe($order, NotificationChannel::WebPush, (string) json_encode($data));

        return response()->json(['ok' => true]);
    }

    public function sms(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'consent' => ['accepted'],
        ]);

        $phone = PhoneNumber::normalize((string) $request->input('phone'));
        if ($phone === null) {
            return back()->withErrors(['phone' => __('Bitte gib eine gültige Handynummer ein.')]);
        }
        if (! $this->accepts($order, NotificationChannel::Sms)) {
            return back()->withErrors(['phone' => __('Das hat nicht geklappt.')]);
        }

        $this->subscribe($order, NotificationChannel::Sms, $phone);

        return back();
    }

    private function accepts(Order $order, NotificationChannel $channel): bool
    {
        return $order->isQueued()
            && in_array($order->status, [OrderStatus::New, OrderStatus::InProgress, OrderStatus::Ready], true)
            && $order->notificationSubscriptions()->where('channel', $channel)->count() < self::MAX_PER_CHANNEL;
    }

    private function subscribe(Order $order, NotificationChannel $channel, string $target): void
    {
        $subscription = $order->notificationSubscriptions()->create([
            'channel' => $channel,
            'target' => $target,
            'status' => NotificationStatus::Active,
        ]);

        // subscribed while the pizza is already waiting
        if ($order->status === OrderStatus::Ready) {
            NotifyCustomerWhenReady::dispatchFor([$subscription], immediately: true);
        }
    }
}
