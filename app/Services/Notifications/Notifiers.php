<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;

final class Notifiers
{
    public function __construct(
        private readonly WebPushNotifier $webPush,
        private readonly WhatsAppNotifier $whatsApp,
        private readonly SmsNotifier $sms,
    ) {}

    public function for(NotificationChannel $channel): CustomerNotifier
    {
        return match ($channel) {
            NotificationChannel::WebPush => $this->webPush,
            NotificationChannel::WhatsApp => $this->whatsApp,
            NotificationChannel::Sms => $this->sms,
        };
    }
}
