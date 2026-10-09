<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use DomainException;

class InvalidTransition extends DomainException
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self(sprintf('Transition %s -> %s not allowed.', $from->value, $to->value));
    }

    public static function notQueued(): self
    {
        return new self('Order is not in the queue yet.');
    }

    public static function nothingToUndo(): self
    {
        return new self('Nothing to undo.');
    }
}
