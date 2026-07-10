<?php

namespace App\Enums;

enum RoutingDeliveryMode: string
{
    case Immediate = 'immediate';
    case Digest = 'digest';
    case Deferred = 'deferred';
    case Skip = 'skip';
}
