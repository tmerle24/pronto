<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Fiscal\FiscalRecorder;
use App\Services\Fiscal\FiskalyFiscalRecorder;
use App\Services\Fiscal\NullFiscalRecorder;
use App\Services\Notifications\CloudApiWhatsAppProvider;
use App\Services\Notifications\LogPushSender;
use App\Services\Notifications\LogSmsProvider;
use App\Services\Notifications\LogWhatsAppProvider;
use App\Services\Notifications\MinishlinkPushSender;
use App\Services\Notifications\PushSender;
use App\Services\Notifications\SevenSmsProvider;
use App\Services\Notifications\SmsProvider;
use App\Services\Notifications\WhatsAppProvider;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Minishlink\WebPush\WebPush;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, fn (): StripeClient => new StripeClient((string) config('services.stripe.secret')));

        $this->app->singleton(PushSender::class, function (): PushSender {
            $public = config('pronto.webpush.public_key');
            $private = config('pronto.webpush.private_key');
            if (blank($public) || blank($private)) {
                return new LogPushSender;
            }

            return new MinishlinkPushSender(new WebPush(['VAPID' => [
                'subject' => (string) config('pronto.webpush.subject'),
                'publicKey' => (string) $public,
                'privateKey' => (string) $private,
            ]]));
        });

        $this->app->singleton(WhatsAppProvider::class, function (): WhatsAppProvider {
            $driver = (string) config('pronto.whatsapp.driver');
            if (! in_array($driver, ['meta', '360dialog'], true) || blank(config('pronto.whatsapp.token'))) {
                return new LogWhatsAppProvider;
            }

            return new CloudApiWhatsAppProvider($driver, (string) config('pronto.whatsapp.token'), config('pronto.whatsapp.phone_number_id'));
        });

        $this->app->singleton(SmsProvider::class, function (): SmsProvider {
            if (config('pronto.sms.driver') !== 'seven' || blank(config('pronto.sms.api_key'))) {
                return new LogSmsProvider;
            }

            return new SevenSmsProvider((string) config('pronto.sms.api_key'), (string) config('pronto.sms.sender'));
        });

        $this->app->singleton(FiscalRecorder::class, function (): FiscalRecorder {
            $c = (array) config('pronto.fiscal.fiskaly');
            if (config('pronto.fiscal.driver') !== 'fiskaly' || blank($c['api_key'] ?? null)) {
                return new NullFiscalRecorder;
            }

            return new FiskalyFiscalRecorder((string) $c['api_key'], (string) $c['api_secret'], (string) $c['tss_id'], (string) $c['client_id']);
        });

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

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // market wifi: many guests may share one IP
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
