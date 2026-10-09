<?php

namespace Tests\Unit;

use App\Services\WaitTime\WaitTimeEstimator;
use App\Services\WaitTime\WaitTimeInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WaitTimeEstimatorTest extends TestCase
{
    private WaitTimeEstimator $estimator;

    protected function setUp(): void
    {
        $this->estimator = new WaitTimeEstimator;
    }

    /**
     * @param  list<float>  $samples
     */
    private function input(float $ahead, float $own, array $samples = [], int $pause = 0, float $elapsed = 0.0): WaitTimeInput
    {
        return new WaitTimeInput(
            ovenSlots: 2,
            startMinutesPerBake: 4,
            bufferMinutes: 3,
            unitsAhead: $ahead,
            ownUnits: $own,
            samples: $samples,
            pauseMinutes: $pause,
            elapsedMinutes: $elapsed,
        );
    }

    public function test_start_formula(): void
    {
        // ceil((3 + 2) / 2) = 3 passes x 4 min + 3 buffer = 15
        $range = $this->estimator->estimate($this->input(3, 2));

        $this->assertSame(15.0, $range->minutes);
        $this->assertSame([15, 20], [$range->low, $range->high]);
    }

    public function test_slices_count_partially_and_drinks_not(): void
    {
        // 4 slices = 1 unit, 1 pass
        $this->assertSame(7.0, $this->estimator->estimate($this->input(0, 1.0))->minutes);
        // drinks only: just the buffer
        $this->assertSame(3.0, $this->estimator->estimate($this->input(0, 0))->minutes);
    }

    public function test_float_noise_does_not_add_a_pass(): void
    {
        // 0.25 x 8 slices can end up as 2.0000000001
        $range = $this->estimator->estimate($this->input(0.1 + 0.2 + 0.7, 1.0 + 1e-12));

        $this->assertSame(7.0, $range->minutes);
    }

    public function test_pause_is_added(): void
    {
        $this->assertSame(17.0, $this->estimator->estimate($this->input(0, 1, pause: 10))->minutes);
    }

    public function test_elapsed_oven_time_is_subtracted_but_never_negative(): void
    {
        $this->assertSame(4.0, $this->estimator->estimate($this->input(0, 2, elapsed: 3))->minutes);
        $this->assertSame(3.0, $this->estimator->estimate($this->input(0, 2, elapsed: 10))->minutes);
    }

    /**
     * @return array<string, array{float, int, int}>
     */
    public static function ranges(): array
    {
        return [
            'exact multiple' => [10.0, 10, 15],
            'between' => [12.0, 10, 15],
            'small' => [3.0, 0, 5],
            'zero' => [0.0, 0, 5],
        ];
    }

    #[DataProvider('ranges')]
    public function test_range_rounded_to_five_minutes(float $buffer, int $low, int $high): void
    {
        $input = new WaitTimeInput(2, 4, (int) $buffer, 0, 0);

        $range = $this->estimator->estimate($input);

        $this->assertSame([$low, $high], [$range->low, $range->high]);
    }

    public function test_start_value_until_five_samples(): void
    {
        $this->assertSame(4.0, $this->estimator->minutesPerBake(4, [8.0, 8.0, 8.0, 8.0]));
    }

    public function test_learned_value_replaces_start_value_gradually(): void
    {
        $five = $this->estimator->minutesPerBake(4, array_fill(0, 5, 8.0));
        $seven = $this->estimator->minutesPerBake(4, array_fill(0, 7, 8.0));
        $ten = $this->estimator->minutesPerBake(4, array_fill(0, 10, 8.0));

        $this->assertEqualsWithDelta(4.0 + 4.0 / 6, $five, 0.0001);
        $this->assertGreaterThan($five, $seven);
        $this->assertSame(8.0, $ten);
    }

    public function test_recent_samples_weigh_more(): void
    {
        $slower = $this->estimator->minutesPerBake(4, [...array_fill(0, 10, 4.0), 8.0, 8.0]);
        $faster = $this->estimator->minutesPerBake(4, [...array_fill(0, 10, 8.0), 4.0, 4.0]);

        $this->assertGreaterThan(5.0, $slower);
        $this->assertLessThan(7.0, $faster);
    }

    public function test_implausible_samples_are_ignored(): void
    {
        $this->assertSame(4.0, $this->estimator->minutesPerBake(4, [0.1, 45.0, 90.0, 0.2, 60.0, 5.0]));
    }

    public function test_sample_normalised_per_pass(): void
    {
        $this->assertSame(4.0, WaitTimeEstimator::sample(4.0, 2.0, 2));
        $this->assertSame(3.0, WaitTimeEstimator::sample(6.0, 3.0, 2));
        $this->assertSame(5.0, WaitTimeEstimator::sample(5.0, 0.25, 2));
        $this->assertNull(WaitTimeEstimator::sample(5.0, 0.0, 2));
    }
}
