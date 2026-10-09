<?php

namespace App\Providers;

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, fn (): StripeClient => new StripeClient((string) config('services.stripe.secret')));

        $this->app->singleton(PaymentGateway::class, function (): PaymentGateway {
            if (blank(config('services.stripe.secret'))) {
                return new FakeGateway;
            }

            return new StripeGateway($this->app->make(StripeClient::class), (string) config('services.stripe.webhook_secret'));
        });
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());

        // market wifi: many guests may share one IP
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
