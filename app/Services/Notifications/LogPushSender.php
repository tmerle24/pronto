<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

/** Without VAPID keys. */
class LogPushSender implements PushSender
{
    public function send(array $subscription, string $payload): void
    {
        Log::info('webpush (log driver)', ['endpoint' => $subscription['endpoint'] ?? null, 'payload' => $payload]);
    }
}
