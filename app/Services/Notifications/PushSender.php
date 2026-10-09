<?php

namespace App\Services\Notifications;

interface PushSender
{
    /**
     * @param  array<string, mixed>  $subscription  browser PushSubscription JSON
     *
     * @throws NotificationFailed
     */
    public function send(array $subscription, string $payload): void;
}
