<?php

namespace App\Listeners;

use App\Enums\PaymentStatus;
use App\Events\OrderPaymentChanged;
use App\Jobs\RecordFiscalTransaction;
use App\Services\Fiscal\FiscalKind;
use App\Services\Fiscal\FiscalRecorder;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Every completed payment (and refund) goes to the fiscal recorder. */
class RecordFiscalTransactions implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly FiscalRecorder $recorder) {}

    public function handle(OrderPaymentChanged $event): void
    {
        if (! $this->recorder->isActive()) {
            return;
        }

        $kind = match ($event->order->payment_status) {
            PaymentStatus::Paid => FiscalKind::Sale,
            PaymentStatus::Refunded => FiscalKind::Refund,
            default => null,
        };
        if ($kind === null) {
            return;
        }

        // same grace period as notifications: undo on the tablet stays clean
        RecordFiscalTransaction::dispatch($event->order->id, $kind)->delay((int) config('pronto.notifications.delay_seconds'));
    }
}
