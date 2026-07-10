<?php

namespace App\Enums;

enum RoutingTargetType: string
{
    case TelegramModeration = 'telegram_moderation';
    case TelegramDelivery = 'telegram_delivery';
    case Email = 'email';
    case Slack = 'slack';

    public function isTelegram(): bool
    {
        return in_array($this, [self::TelegramModeration, self::TelegramDelivery], true);
    }

    public function isImplemented(): bool
    {
        return $this->isTelegram();
    }
}
