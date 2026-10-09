<?php

namespace App\Support;

use App\Enums\Labelled;
use BackedEnum;

final class EnumOptions
{
    /**
     * Select options value => German label.
     *
     * @param  class-string<BackedEnum&Labelled>  $enum
     * @return array<string, string>
     */
    public static function for(string $enum): array
    {
        $options = [];
        foreach ($enum::cases() as $case) {
            $options[(string) $case->value] = $case->label();
        }

        return $options;
    }
}
