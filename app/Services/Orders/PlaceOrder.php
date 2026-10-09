<?php

namespace App\Services\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an order from cart lines. Prices always come from the database.
 */
class PlaceOrder
{
    public const MAX_QUANTITY_PER_LINE = 20;

    // no 0/O, 1/I/L, 2/Z, 5/S, 8/B
    private const SHORT_CODE_ALPHABET = 'ACDEFHJKMNPRTUVWXY34679';

    public function __construct(
        private readonly PaymentGateway $payments,
        private readonly OrderQueue $queue,
        private readonly OrderPayments $orderPayments,
    ) {}

    /**
     * @param  list<OrderLine>  $lines
     */
    public function __invoke(
        Event $event,
        array $lines,
        PaymentMethod $method,
        OrderSource $source = OrderSource::Qr,
        string $locale = 'de',
        ?User $user = null,
    ): Order {
        $this->guard($event, $method, $source);
        $items = $this->resolveItems($event, $lines);

        $order = DB::transaction(function () use ($event, $lines, $items, $method, $source, $locale, $user): Order {
            $order = new Order([
                'event_id' => $event->id,
                'public_token' => Str::random(40),
                'short_code' => $this->uniqueShortCode($event),
                'payment_method' => $method,
                'payment_status' => PaymentStatus::Pending,
                'source' => $source,
                'total_cents' => 0,
                'locale' => $locale,
                'created_by_user_id' => $user?->id,
            ]);
            $order->save();

            $total = 0;
            $units = 0.0;
            foreach ($lines as $line) {
                $item = $items[$line->menuItemId];
                $order->items()->create([
                    'menu_item_id' => $item->id,
                    'name' => $item->name,
                    'variant' => $item->variant,
                    'unit_price_cents' => $item->price_cents,
                    'unit_deposit_cents' => $item->deposit_cents,
                    'vat_rate_bp' => $item->vat_rate_bp,
                    'unit_oven_units' => $item->oven_units,
                    'quantity' => $line->quantity,
                ]);
                $total += ($item->price_cents + $item->deposit_cents) * $line->quantity;
                $units += (float) $item->oven_units * $line->quantity;
            }

            $order->total_cents = $total;
            $order->oven_units = number_format($units, 2, '.', '');
            $order->save();

            $order->events()->create([
                'type' => OrderEventType::Created,
                'user_id' => $user?->id,
                'meta' => ['method' => $method->value, 'source' => $source->value, 'total_cents' => $total],
            ]);

            return $order;
        });

        return match ($method) {
            PaymentMethod::Online => $this->startOnlinePayment($order),
            PaymentMethod::Cash => $this->queue->release($order),
            PaymentMethod::CounterCard => $this->queue->release($this->orderPayments->markPaid($order, $user)),
        };
    }

    private function guard(Event $event, PaymentMethod $method, OrderSource $source): void
    {
        if (! $event->is_active) {
            throw new OrderRejected('Gerade ist kein Markt geöffnet.');
        }
        // the counter may still take orders during a pause
        if ($source === OrderSource::Qr && ! $event->isOpenForOrders()) {
            throw new OrderRejected('Gerade nehmen wir keine Bestellungen an.');
        }
        if ($source === OrderSource::Qr && $method === PaymentMethod::CounterCard) {
            throw new OrderRejected('Diese Zahlungsart gibt es nur an der Theke.');
        }
        if ($source === OrderSource::Qr && $method === PaymentMethod::Cash && ! $event->cash_allowed) {
            throw new OrderRejected('Barzahlung ist gerade nicht möglich.');
        }
    }

    /**
     * @param  list<OrderLine>  $lines
     * @return array<int, MenuItem>
     */
    private function resolveItems(Event $event, array $lines): array
    {
        if ($lines === []) {
            throw new OrderRejected('Dein Warenkorb ist leer.');
        }

        $ids = array_map(fn (OrderLine $l) => $l->menuItemId, $lines);
        if (count($ids) !== count(array_unique($ids))) {
            throw new OrderRejected('Ungültige Bestellung.');
        }

        $items = MenuItem::query()->forEditions($event->editions)->whereKey($ids)->get()->keyBy('id');

        foreach ($lines as $line) {
            $item = $items->get($line->menuItemId);
            if ($item === null) {
                throw new OrderRejected('Ungültige Bestellung.');
            }
            if (! $item->isOrderable()) {
                throw new OrderRejected('Leider ausverkauft: :name', ['name' => $item->name]);
            }
            if ($line->quantity < 1 || $line->quantity > self::MAX_QUANTITY_PER_LINE) {
                throw new OrderRejected('Ungültige Menge.');
            }
        }

        return $items->all();
    }

    private function startOnlinePayment(Order $order): Order
    {
        $order->stripe_payment_intent_id = $this->payments->createPayment($order);
        $order->save();

        return $order;
    }

    private function uniqueShortCode(Event $event): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= self::SHORT_CODE_ALPHABET[random_int(0, strlen(self::SHORT_CODE_ALPHABET) - 1)];
            }
        } while (Order::query()->where('event_id', $event->id)->where('short_code', $code)->exists());

        return $code;
    }
}
