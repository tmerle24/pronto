<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

class LogWhatsAppProvider implements WhatsAppProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function sendText(string $waId, string $text): ?string
    {
        Log::info('whatsapp (log driver)', ['to' => substr($waId, 0, 4).'…', 'text' => $text]);

        return null;
    }
}
