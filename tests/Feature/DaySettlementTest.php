<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\EventCost;
use App\Models\MarketDay;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Services\Staffing\DaySettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaySettlementTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private MarketDay $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(20, 0));
        $this->event = Event::factory()->create();
        $this->day = MarketDay::factory()->for($this->event)->create(['date' => today()]);
        MarketDay::factory()->for($this->event)->create(['date' => today()->addDay()]);
    }

    /** @param  array<string, mixed>  $state */
    private function order(PaymentMethod $method, int $price, int $quantity, int $vatBp, int $deposit = 0, array $state = []): Order
    {
        $order = Order::factory()->for($this->event)->paid()->create(array_merge([
            'payment_method' => $method,
            'queued_at' => now()->setTime(12, 0),
            'total_cents' => ($price + $deposit) * $quantity,
        ], $state));
        $order->items()->create([
            'menu_item_id' => MenuItem::factory()->create()->id,
            'name' => 'Nonno Classico',
            'unit_price_cents' => $price,
            'unit_deposit_cents' => $deposit,
            'vat_rate_bp' => $vatBp,
            'unit_oven_units' => '1.00',
            'quantity' => $quantity,
        ]);

        return $order;
    }

    private function shift(User $user, string $from, string $until): void
    {
        Shift::query()->create(['market_day_id' => $this->day->id, 'user_id' => $user->id, 'starts_at' => $from, 'ends_at' => $until]);
    }

    public function test_profit_after_vat_fees_and_costs_is_shared_by_hours(): void
    {
        $this->order(PaymentMethod::Cash, 650, 100, 700);
        $this->order(PaymentMethod::TapToPay, 225, 2, 1900, deposit: 25);
        // not counted: unpaid, other day
        $this->order(PaymentMethod::Online, 650, 5, 700, state: ['payment_status' => PaymentStatus::Pending]);
        $this->order(PaymentMethod::Cash, 650, 5, 700, state: ['queued_at' => now()->addDay()->setTime(12, 0)]);
        EventCost::query()->create(['event_id' => $this->event->id, 'label' => 'Standgebühr', 'amount_cents' => 35000]);
        EventCost::query()->create(['event_id' => $this->event->id, 'market_day_id' => $this->day->id, 'label' => 'Einkauf', 'amount_cents' => 10000]);

        $timo = User::factory()->create();
        $helper = User::factory()->create();
        $papa = User::factory()->create();
        $this->shift($timo, '09:00', '19:00');
        $this->shift($helper, '11:00', '17:00');
        $this->shift($papa, '09:00', '10:00');
        $this->shift($papa, '18:00', '19:00');

        $s = (new DaySettlement($this->day))->summary();

        $this->assertSame(2, $s['orders']);
        $this->assertSame(65450, $s['gross_cents']);
        $this->assertSame(4324, $s['vat_cents']);
        $this->assertSame(33, $s['card_fee_cents']);
        $this->assertSame([17500, 10000], array_column($s['costs'], 'cents'));
        $this->assertSame(33593, $s['profit_cents']);
        $this->assertSame(18.0, $s['hours']);
        $this->assertSame(
            [[$timo->id, 10.0, 18663], [$helper->id, 6.0, 11198], [$papa->id, 2.0, 3732]],
            array_map(fn (array $p) => [$p['user_id'], $p['hours'], $p['payout_cents']], $s['people']),
        );
    }

    public function test_a_loss_pays_nobody(): void
    {
        $this->order(PaymentMethod::Cash, 650, 2, 700);
        EventCost::query()->create(['event_id' => $this->event->id, 'label' => 'Standgebühr', 'amount_cents' => 35000]);
        $this->shift(User::factory()->create(), '10:00', '18:00');

        $s = (new DaySettlement($this->day))->summary();

        $this->assertLessThan(0, $s['profit_cents']);
        $this->assertSame(0, $s['people'][0]['payout_cents']);
    }
}
