<?php

namespace App\Services\Notifications;

use App\Models\Order;

/** Texts sent to guests, in the order's language. */
final class Messages
{
    public static function ready(Order $order): string
    {
        return __('Pronto! Deine Nummer :n ist fertig. Komm zur Ausgabe. Buon appetito!', ['n' => $order->pickup_number], $order->locale);
    }

    public static function registered(Order $order): string
    {
        return __('Danke! Wir melden uns, wenn Nummer :n fertig ist.', ['n' => $order->pickup_number], $order->locale);
    }
}
