<?php

namespace App\Services\Fiscal;

use App\Models\Order;

/**
 * Hook for a technical security device (TSE). Whether one is required is
 * open with the tax advisor; NullFiscalRecorder is the default.
 */
interface FiscalRecorder
{
    /**
     * Records a paid sale (or a refund) and returns data for the receipt.
     *
     * @return array<string, mixed>|null
     */
    public function record(Order $order, FiscalKind $kind): ?array;

    public function isActive(): bool;
}
