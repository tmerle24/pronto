<?php

namespace App\Services\Orders;

use DomainException;

/** Shown to the guest; message is a translation key. */
class OrderRejected extends DomainException
{
    /**
     * @param  array<string, string>  $replace
     */
    public function __construct(string $message, public readonly array $replace = [])
    {
        parent::__construct($message);
    }

    public function translated(): string
    {
        return __($this->getMessage(), $this->replace);
    }
}
