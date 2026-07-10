<?php

namespace App\Enums;

enum RoutingConditionOperator: string
{
    case Equals = 'eq';
    case In = 'in';
    case NotIn = 'not_in';
    case Between = 'between';
    case NotBetween = 'not_between';
    case Gte = 'gte';
    case Lte = 'lte';
}
