<?php

namespace App\Console\Commands;

use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Models\Event;
use App\Models\MenuItem;
use App\Services\Orders\OrderLine;
use App\Services\Orders\PlaceOrder;
use Illuminate\Console\Command;

/** Local test data for queue and display. Never in production. */
class DemoOrders extends Command
{
    protected $signature = 'pronto:demo-orders {count=3}';

    protected $description = 'Place random demo orders for the active event (not in production)';

    public function handle(PlaceOrder $placeOrder): int
    {
        if (app()->isProduction()) {
            $this->error('Not in production.');

            return self::FAILURE;
        }

        $event = Event::current();
        if ($event === null) {
            $this->error('No active event.');

            return self::FAILURE;
        }

        $items = MenuItem::query()->forEditions($event->editions)->where('is_sold_out', false)->get();
        for ($i = 0; $i < (int) $this->argument('count'); $i++) {
            $lines = array_values($items->random(min(2, $items->count()))
                ->map(fn (MenuItem $item) => new OrderLine($item->id, random_int(1, 3)))
                ->all());
            $method = random_int(0, 2) === 0 ? PaymentMethod::Cash : PaymentMethod::CounterCard;
            $order = $placeOrder($event, $lines, $method, OrderSource::Counter);
            $this->line("Nr. {$order->pickup_number} ({$method->value})");
        }

        return self::SUCCESS;
    }
}
