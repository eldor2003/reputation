<?php

namespace App\Enums;

enum RoutingPriority: string
{
    case Immediate = 'immediate';
    case Normal = 'normal';
    case Low = 'low';
    case Deferred = 'deferred';
}
