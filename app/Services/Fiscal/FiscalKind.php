<?php

namespace App\Services\Fiscal;

enum FiscalKind: string
{
    case Sale = 'sale';
    case Refund = 'refund';
}
