<?php

namespace Tests;

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stripe\StripeClient;
use Tests\Support\ExplodingStripeClient;

abstract class TestCase extends BaseTestCase
{
    protected FakeGateway $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->app->instance(StripeClient::class, new ExplodingStripeClient);
        $this->payments = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->payments);
    }
}
