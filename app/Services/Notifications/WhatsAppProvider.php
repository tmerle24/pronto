<?php

namespace App\Services\Notifications;

interface WhatsAppProvider
{
    public function name(): string;

    /**
     * Free-form text, only allowed inside the 24 h window after the guest wrote.
     *
     * @return ?string provider message id
     *
     * @throws NotificationFailed
     */
    public function sendText(string $waId, string $text): ?string;
}
