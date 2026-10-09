<?php

namespace App\Services\WaitTime;

final readonly class WaitTimeRange
{
    public function __construct(
        public int $low,
        public int $high,
        // unrounded estimate, for logs and tests
        public float $minutes,
    ) {}

    /** @return array{low: int, high: int} */
    public function toArray(): array
    {
        return ['low' => $this->low, 'high' => $this->high];
    }
}
