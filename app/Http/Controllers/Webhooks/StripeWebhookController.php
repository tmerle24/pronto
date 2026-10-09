<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProcessedWebhook;
use App\Services\Orders\OrderPayments;
use App\Services\Payments\InvalidWebhook;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Source of truth for online payments. Each Stripe event is processed once;
 * a failure rolls back the marker so Stripe's retry runs it again.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, OrderPayments $payments): Response
    {
        try {
            $event = $gateway->parseWebhook($request->getContent(), $request->header('Stripe-Signature'));
        } catch (InvalidWebhook $e) {
            Log::warning('stripe webhook rejected', ['error' => $e->getMessage()]);

            return response('invalid signature', 400);
        }

        if (! in_array($event->type, [WebhookEvent::PAYMENT_SUCCEEDED, WebhookEvent::PAYMENT_FAILED], true)) {
            return response('ignored', 200);
        }

        try {
            DB::transaction(function () use ($event, $payments): void {
                ProcessedWebhook::query()->create([
                    'provider' => 'stripe',
                    'external_id' => $event->id,
                    'type' => $event->type,
                ]);

                $order = Order::query()->where('stripe_payment_intent_id', $event->paymentId)->first();
                if ($order === null) {
                    Log::warning('stripe webhook for unknown payment', ['payment' => $event->paymentId]);

                    return;
                }

                if ($event->type === WebhookEvent::PAYMENT_SUCCEEDED) {
                    $payments->confirmOnline($order);
                }
                // failed attempts: guest may retry on the pay page, expiry cleans up
            });
        } catch (UniqueConstraintViolationException) {
            return response('duplicate', 200);
        }

        return response('ok', 200);
    }
}
