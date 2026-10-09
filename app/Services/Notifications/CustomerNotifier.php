<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Models\NotificationSubscription;

interface CustomerNotifier
{
    public function channel(): NotificationChannel;

    /**
     * @throws NotificationFailed
     */
    public function sendReady(NotificationSubscription $subscription): void;
}
