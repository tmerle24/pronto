<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MenuItem;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function show(?Event $event = null): Response
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
                'variant' => $item->variant?->label(),
                'price_cents' => $item->price_cents,
                'deposit_cents' => $item->deposit_cents,
                'allergens' => $item->allergens,
                'additives' => $item->additives,
                'sold_out' => $item->is_sold_out,
            ]);

        return Inertia::render('Guest/Menu', [
            'event' => $event?->only(['name', 'slug']),
            'items' => $items,
        ]);
    }
}
