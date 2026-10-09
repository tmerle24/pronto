<?php

namespace App\Enums;

enum MenuCategory: string implements Labelled
{
    case Pizza = 'pizza';
    case Flammkuchen = 'flammkuchen';
    case Drink = 'drink';

    public function label(): string
    {
        return match ($this) {
            self::Pizza => 'Pizza',
            self::Flammkuchen => 'Flammkuchen',
            self::Drink => 'Getränke',
        };
    }
}
