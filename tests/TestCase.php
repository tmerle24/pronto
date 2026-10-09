<?php

namespace Tests;

use App\Services\Notifications\PushSender;
use App\Services\Payments\CardTerminal;
use App\Services\Payments\FakeCardTerminal;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Stripe\StripeClient;
use Tests\Support\ExplodingStripeClient;
use Tests\Support\FakePushSender;

abstract class TestCase extends BaseTestCase
{
    protected FakeGateway $payments;

    protected FakeCardTerminal $terminal;

    protected FakePushSender $push;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->app->instance(StripeClient::class, new ExplodingStripeClient);
        $this->payments = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->payments);
        $this->terminal = new FakeCardTerminal;
        $this->app->instance(CardTerminal::class, $this->terminal);

        // no test may reach a real provider (SMS, WhatsApp cost money)
        Http::preventStrayRequests();
        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);
    }
}
