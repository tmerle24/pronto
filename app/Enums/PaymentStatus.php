<?php

namespace App\Enums;

enum PaymentStatus: string implements Labelled
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Offen',
            self::Paid => 'Bezahlt',
            self::Failed => 'Fehlgeschlagen',
            self::Refunded => 'Erstattet',
        };
    }
}
