<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\NotificationSubscription;
use App\Models\Order;

class WhatsAppNotifier implements CustomerNotifier
{
    public function __construct(
        private readonly WhatsAppProvider $provider,
        private readonly PaidMessageGuard $guard,
    ) {}

    public function channel(): NotificationChannel
    {
        return NotificationChannel::WhatsApp;
    }

    public function sendReady(NotificationSubscription $subscription): void
    {
        $this->send((string) $subscription->target, $subscription->order, Messages::ready($subscription->order), 'ready');
    }

    /** Immediate answer when the guest's message arrives. */
    public function confirm(string $waId, Order $order): void
    {
        $this->send($waId, $order, Messages::registered($order), 'registered');
    }

    private function send(string $waId, Order $order, string $text, string $kind): void
    {
        $this->guard->send(
            NotificationChannel::WhatsApp,
            $this->provider->name(),
            $kind,
            $order,
            (int) config('pronto.whatsapp.daily_limit'),
            fn () => $this->provider->sendText($waId, $text),
        );
    }
}
