<?php

namespace App\Http\Controllers\Guest;

use App\Enums\MenuEdition;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MenuItem;
use App\Services\Payments\OnlinePayments;
use App\Services\WaitTime\EventWaitTimes;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function show(OnlinePayments $online, ?Event $event = null): Response
    {
        $event ??= Event::current();
        $locale = app()->getLocale();

        $items = $event === null ? collect() : MenuItem::query()
            ->forEditions($event->editions)
            ->get()
            ->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->localizedName($locale),
                'description' => $item->localizedDescription($locale),
                'category' => $item->category->value,
                'edition' => $item->edition?->value,
                'variant' => $item->variant?->value,
                'icon' => $item->icon,
                'price_cents' => $item->price_cents,
                'deposit_cents' => $item->deposit_cents,
                'allergens' => $item->allergens,
                'additives' => $item->additives,
                'sold_out' => $item->is_sold_out,
            ]);

        return Inertia::render('Guest/Menu', [
            'event' => $event === null ? null : [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'editions' => $event->editions->map(fn (MenuEdition $e) => $e->value)->values(),
                'open' => $event->isOpenForOrders(),
                'paused_minutes' => $event->pauseMinutesLeft(),
                'pause_message' => $event->pause_message,
                'cash_allowed' => $event->cash_allowed,
                'online_allowed' => $online->enabled(),
                'wait' => (new EventWaitTimes($event))->general()->toArray(),
            ],
            'items' => $items,
        ]);
    }
}
