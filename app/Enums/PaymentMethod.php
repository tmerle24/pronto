<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Online = 'online';
    case Cash = 'cash';
    case CounterCard = 'counter_card';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Cash => 'Bar',
            self::CounterCard => 'Karte (Theke)',
        };
    }
}
