<?php

namespace App\Enums;

enum DeliveryChannel: string
{
    case TelegramDelivery = 'telegram_delivery';
    case Email = 'email';
    case Slack = 'slack';

    public function isImplemented(): bool
    {
        return $this === self::TelegramDelivery;
    }
}
