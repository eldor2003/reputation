<?php

namespace App\Enums;

enum TelegramNotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
