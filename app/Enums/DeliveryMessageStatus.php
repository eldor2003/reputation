<?php

namespace App\Enums;

enum DeliveryMessageStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
