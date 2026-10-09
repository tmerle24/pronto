<?php

namespace App\Enums;

enum ItemVariant: string
{
    case Whole = 'whole';
    case Slice = 'slice';

    public function label(): string
    {
        return match ($this) {
            self::Whole => 'ganz',
            self::Slice => 'Stück',
        };
    }
}
