<?php

namespace App\Services\WaitTime;

/**
 * Pure wait time maths, no database.
 *
 * Start: ceil((units ahead + own units) / oven slots) x minutes per bake + buffer.
 * Learning: measured "in oven -> ready" per oven pass, exponential moving
 * average; from 5 samples on it gradually replaces the start value.
 * Output always as a range rounded to 5 minutes, pause added.
 */
final class WaitTimeEstimator
{
    public const ALPHA = 0.3;

    public const MIN_SAMPLES = 5;

    // samples until the learned value fully replaces the start value
    public const FULL_WEIGHT_SAMPLES = 10;

    // plausible minutes per pass; outside = forgotten tap, ignored
    public const SAMPLE_MIN = 0.5;

    public const SAMPLE_MAX = 30.0;

    public function estimate(WaitTimeInput $input): WaitTimeRange
    {
        $slots = max(1, $input->ovenSlots);
        $perBake = $this->minutesPerBake($input->startMinutesPerBake, $input->samples);

        $units = max(0.0, $input->unitsAhead) + max(0.0, $input->ownUnits);
        $passes = $units > 0 ? (int) ceil(round($units / $slots, 4)) : 0;

        $oven = max(0.0, $passes * $perBake - $input->elapsedMinutes);
        $minutes = $oven + max(0, $input->bufferMinutes) + max(0, $input->pauseMinutes);

        return $this->range($minutes);
    }

    /**
     * @param  list<float>  $samples
     */
    public function minutesPerBake(int $start, array $samples): float
    {
        $valid = array_values(array_filter($samples, fn (float $s) => $s >= self::SAMPLE_MIN && $s <= self::SAMPLE_MAX));
        $count = count($valid);
        if ($count < self::MIN_SAMPLES) {
            return (float) $start;
        }

        $ema = $valid[0];
        foreach (array_slice($valid, 1) as $sample) {
            $ema = self::ALPHA * $sample + (1 - self::ALPHA) * $ema;
        }

        $weight = min(1.0, ($count - self::MIN_SAMPLES + 1) / (self::FULL_WEIGHT_SAMPLES - self::MIN_SAMPLES + 1));

        return $weight * $ema + (1 - $weight) * $start;
    }

    /** Duration of one order normalised to one oven pass. */
    public static function sample(float $minutesInOven, float $units, int $ovenSlots): ?float
    {
        if ($units <= 0) {
            return null;
        }

        return $minutesInOven / max(1, (int) ceil(round($units / max(1, $ovenSlots), 4)));
    }

    private function range(float $minutes): WaitTimeRange
    {
        $low = (int) (floor($minutes / 5) * 5);
        $high = (int) (ceil($minutes / 5) * 5);
        if ($high === $low) {
            $high += 5;
        }

        return new WaitTimeRange($low, $high, $minutes);
    }
}
