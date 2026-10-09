<?php

namespace App\Services\Notifications;

interface SmsProvider
{
    public function name(): string;

    /**
     * @return ?string provider message id
     *
     * @throws NotificationFailed
     */
    public function send(string $phone, string $text): ?string;
}
