<?php

namespace Tests\Feature;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Orders\OrderQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ExpireUnpaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_online_orders_expire_after_30_minutes(): void
    {
        $old = Order::factory()->create(['stripe_payment_intent_id' => 'pi_old']);
        $this->travel(20)->minutes();
        $fresh = Order::factory()->create();
        $cash = app(OrderQueue::class)->release(Order::factory()->cash()->create());
        $this->travel(11)->minutes();

        $this->assertSame(0, Artisan::call('orders:expire-unpaid'));

        $old->refresh();
        $this->assertSame(OrderStatus::Cancelled, $old->status);
        $this->assertSame(PaymentStatus::Failed, $old->payment_status);
        $this->assertTrue($old->events()->where('type', OrderEventType::Expired)->exists());
        $this->assertContains('cancel:'.$old->id, $this->payments->calls);

        $this->assertSame(OrderStatus::New, $fresh->refresh()->status);
        $this->assertSame(OrderStatus::New, $cash->refresh()->status);
    }
}
