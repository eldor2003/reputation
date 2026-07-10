<?php

namespace App\Enums;

enum DeliveryDigestStatus: string
{
    case Pending = 'pending';
    case Generated = 'generated';
    case Sent = 'sent';
    case Failed = 'failed';
}
