<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phone, string $text): ?string
    {
        Log::info('sms (log driver)', ['to' => substr($phone, 0, 5).'…', 'text' => $text]);

        return null;
    }
}
