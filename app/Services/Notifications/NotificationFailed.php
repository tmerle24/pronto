<?php

namespace App\Services\Notifications;

use RuntimeException;

class NotificationFailed extends RuntimeException
{
    // retrying will not help (expired push subscription, invalid number, limit reached)
    public bool $permanent = false;

    public static function permanent(string $message): self
    {
        $e = new self($message);
        $e->permanent = true;

        return $e;
    }
}
