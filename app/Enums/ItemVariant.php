<?php

namespace App\Enums;

enum ItemVariant: string implements Labelled
{
    case Whole = 'whole';
    case Slice = 'slice';
    case Gratinated = 'gratinated';

    public function label(): string
    {
        return match ($this) {
            self::Whole => 'ganz',
            self::Slice => 'Stück',
            self::Gratinated => 'gratiniert',
        };
    }
}
