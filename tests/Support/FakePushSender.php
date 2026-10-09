<?php

namespace Tests\Support;

use App\Services\Notifications\NotificationFailed;
use App\Services\Notifications\PushSender;

class FakePushSender implements PushSender
{
    /** @var list<array{endpoint: mixed, payload: string}> */
    public array $sent = [];

    public ?NotificationFailed $failWith = null;

    public function send(array $subscription, string $payload): void
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
        $this->sent[] = ['endpoint' => $subscription['endpoint'] ?? null, 'payload' => $payload];
    }
}
