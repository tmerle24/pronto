<?php

namespace App\Services\Notifications;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class MinishlinkPushSender implements PushSender
{
    public function __construct(private readonly WebPush $webPush) {}

    public function send(array $subscription, string $payload): void
    {
        $report = $this->webPush->sendOneNotification(Subscription::create($subscription), $payload, ['TTL' => 900, 'urgency' => 'high']);

        if ($report->isSubscriptionExpired()) {
            throw NotificationFailed::permanent('Push subscription expired.');
        }
        if (! $report->isSuccess()) {
            throw new NotificationFailed($report->getReason());
        }
    }
}
