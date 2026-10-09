<?php

namespace App\Enums;

enum OrderEventType: string
{
    case Created = 'created';
    case Queued = 'queued';
    case StatusChanged = 'status_changed';
    case PaymentChanged = 'payment_changed';
    case CustomerNotified = 'customer_notified';
    case Expired = 'expired';
}
