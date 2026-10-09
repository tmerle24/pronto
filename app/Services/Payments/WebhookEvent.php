<?php

namespace App\Services\Payments;

final readonly class WebhookEvent
{
    public const PAYMENT_SUCCEEDED = 'payment_intent.succeeded';

    public const PAYMENT_FAILED = 'payment_intent.payment_failed';

    public function __construct(
        public string $id,
        public string $type,
        // payment intent id
        public ?string $paymentId,
    ) {}
}
