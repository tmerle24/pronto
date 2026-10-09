<?php

namespace App\Services\Fiscal;

use App\Models\Order;

class NullFiscalRecorder implements FiscalRecorder
{
    public function record(Order $order, FiscalKind $kind): ?array
    {
        return null;
    }

    public function isActive(): bool
    {
        return false;
    }
}
