<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\NotificationSubscription;

class SmsNotifier implements CustomerNotifier
{
    public function __construct(
        private readonly SmsProvider $provider,
        private readonly PaidMessageGuard $guard,
    ) {}

    public function channel(): NotificationChannel
    {
        return NotificationChannel::Sms;
    }

    public function sendReady(NotificationSubscription $subscription): void
    {
        $order = $subscription->order;

        $this->guard->send(
            NotificationChannel::Sms,
            $this->provider->name(),
            'ready',
            $order,
            (int) config('pronto.sms.daily_limit'),
            fn () => $this->provider->send((string) $subscription->target, Messages::ready($order)),
        );
    }
}
