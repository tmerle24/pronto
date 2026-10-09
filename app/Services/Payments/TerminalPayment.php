<?php

namespace App\Services\Payments;

final readonly class TerminalPayment
{
    public const SUCCEEDED = 'succeeded';

    public const REQUIRES_CAPTURE = 'requires_capture';

    public const CANCELED = 'canceled';

    public function __construct(
        public string $id,
        public string $status,
        public int $amountCents,
        public string $currency,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === self::SUCCEEDED;
    }
}
