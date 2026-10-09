<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Jobs\RecordFiscalTransaction;
use App\Models\Event;
use App\Models\Order;
use App\Services\Fiscal\FiscalKind;
use App\Services\Fiscal\FiscalRecorder;
use App\Services\Orders\OrderPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FiscalRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_recorder_is_the_default_and_dispatches_nothing(): void
    {
        Queue::fake();
        app(OrderPayments::class)->markPaid(Order::factory()->for(Event::factory())->create());

        $this->assertFalse(app(FiscalRecorder::class)->isActive());
        Queue::assertNotPushed(RecordFiscalTransaction::class);
    }

    public function test_active_recorder_is_called_for_every_payment_and_stored(): void
    {
        $recorder = new class implements FiscalRecorder
        {
            /** @var list<string> */
            public array $calls = [];

            public function record(Order $order, FiscalKind $kind): array
            {
                $this->calls[] = $kind->value.':'.$order->id;

                return ['tss_serial' => 'TSS1', 'number' => 7, 'signature' => 'abc'];
            }

            public function isActive(): bool
            {
                return true;
            }
        };
        $this->app->instance(FiscalRecorder::class, $recorder);

        $order = app(OrderPayments::class)->markPaid(Order::factory()->for(Event::factory())->create());

        $this->assertSame(['sale:'.$order->id], $recorder->calls);
        $this->assertSame('TSS1', $order->refresh()->fiscal_data['sale']['tss_serial'] ?? null);

        // idempotent: a retry does not sign twice
        app()->call([new RecordFiscalTransaction($order->id), 'handle']);
        $this->assertCount(1, $recorder->calls);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }
}
