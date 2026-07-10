<?php

namespace App\Enums;

enum DeliveryDigestItemStatus: string
{
    case Queued = 'queued';
    case Included = 'included';
    case Sent = 'sent';
    case Failed = 'failed';
}
