<?php

namespace Tests\Support;

use LogicException;
use Stripe\StripeClient;

/** Bound in every test: a real Stripe call fails loudly instead of costing money. */
class ExplodingStripeClient extends StripeClient
{
    public function __construct()
    {
        parent::__construct('sk_test_never_used');
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function request($method, $path, $params, $opts): never
    {
        throw new LogicException("Real Stripe call in a test: {$method} {$path}");
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function requestStream($method, $path, $readBodyChunkCallable, $params, $opts): never
    {
        throw new LogicException("Real Stripe call in a test: {$method} {$path}");
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function requestCollection($method, $path, $params, $opts): never
    {
        throw new LogicException("Real Stripe call in a test: {$method} {$path}");
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts
     */
    public function requestSearchResult($method, $path, $params, $opts): never
    {
        throw new LogicException("Real Stripe call in a test: {$method} {$path}");
    }
}
