<?php

namespace App\Enums;

enum PaymentMethod: string implements Labelled
{
    case Online = 'online';
    case Cash = 'cash';
    case CounterCard = 'counter_card';
    // Stripe Terminal on the counter phone (native app)
    case TapToPay = 'tap_to_pay';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Cash => 'Bar',
            self::CounterCard => 'Karte (Theke)',
            self::TapToPay => 'Karte (Tap to Pay)',
        };
    }
}
