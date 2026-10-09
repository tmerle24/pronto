<?php

namespace App\Enums;

enum MenuEdition: string implements Labelled
{
    case Pizza = 'pizza';
    case Flammkuchen = 'flammkuchen';

    public function label(): string
    {
        return match ($this) {
            self::Pizza => 'Pizza',
            self::Flammkuchen => 'Flammkuchen',
        };
    }
}
