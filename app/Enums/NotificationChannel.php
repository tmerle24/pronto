<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case WebPush = 'webpush';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
}
