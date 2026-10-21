<?php

namespace Tests\Support;

use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Util\Util;

/** Answers with canned objects and records every call, never touches the network. */
class RecordingStripeClient extends StripeClient
{
    /** @var list<array{method: string, path: string, params: array<string, mixed>, opts: mixed}> */
    public array $requests = [];

    /** @param  array<string, array<string, mixed>>  $responses  "method path" => object */
    public function __construct(private readonly array $responses = [])
    {
        parent::__construct('sk_test_never_used');
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function request($method, $path, $params, $opts): StripeObject
    {
        $this->requests[] = ['method' => $method, 'path' => $path, 'params' => $params, 'opts' => $opts];

        /** @var StripeObject */
        return Util::convertToStripeObject($this->responses["{$method} {$path}"] ?? ['id' => 'x'], []);
    }
}
