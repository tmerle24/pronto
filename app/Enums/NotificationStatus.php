<?php

namespace App\Enums;

enum NotificationStatus: string
{
    // whatsapp: link opened, waiting for the guest's first message
    case Pending = 'pending';
    case Active = 'active';
    case Sent = 'sent';
    case Failed = 'failed';
}
