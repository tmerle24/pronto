<?php

namespace App\Support;

/** "09:30" or "09:30:00" from a time column, as minutes after midnight and back. */
final class TimeOfDay
{
    public static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [1 => 0]);

        return $h * 60 + $m;
    }

    public static function format(string $time): string
    {
        return substr($time, 0, 5);
    }

    /** Hours between two times, 0 if the end is not after the start. */
    public static function hours(string $from, string $until): float
    {
        return max(0, self::minutes($until) - self::minutes($from)) / 60;
    }
}
