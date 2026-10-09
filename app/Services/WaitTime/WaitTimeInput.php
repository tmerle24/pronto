<?php

namespace App\Services\WaitTime;

final readonly class WaitTimeInput
{
    /**
     * @param  list<float>  $samples  measured minutes per oven pass, oldest first
     */
    public function __construct(
        public int $ovenSlots,
        public int $startMinutesPerBake,
        public int $bufferMinutes,
        public float $unitsAhead,
        public float $ownUnits,
        public array $samples = [],
        public int $pauseMinutes = 0,
        // own order already in the oven for this long
        public float $elapsedMinutes = 0.0,
    ) {}
}
