<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Fiscal\FiscalKind;
use App\Services\Fiscal\FiscalRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordFiscalTransaction implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 300];

    public function __construct(
        public readonly int $orderId,
        public readonly FiscalKind $kind = FiscalKind::Sale,
    ) {}

    public function handle(FiscalRecorder $recorder): void
    {
        $order = Order::query()->with('items')->find($this->orderId);
        if ($order === null) {
            return;
        }

        $data = $order->fiscal_data ?? [];
        // undone within the grace period, or already recorded
        if (isset($data[$this->kind->value])
            || ($this->kind === FiscalKind::Sale && $order->payment_status !== PaymentStatus::Paid)
            || ($this->kind === FiscalKind::Refund && $order->payment_status !== PaymentStatus::Refunded)) {
            return;
        }

        $result = $recorder->record($order, $this->kind);
        if ($result === null) {
            return;
        }

        $data[$this->kind->value] = $result;
        $order->fiscal_data = $data;
        $order->save();
    }
}
