<?php

namespace App\Enums;

enum TelegramDestination: string
{
    case Moderation = 'telegram_moderation';
    case Delivery = 'telegram_delivery';
}
