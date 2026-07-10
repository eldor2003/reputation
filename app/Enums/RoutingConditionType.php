<?php

namespace App\Enums;

enum RoutingConditionType: string
{
    case Project = 'project';
    case Person = 'person';
    case ThreatLevel = 'threat_level';
    case SourceType = 'source_type';
    case SourceId = 'source_id';
    case TimeOfDay = 'time_of_day';
    case WorkingHours = 'working_hours';
    case NightMode = 'night_mode';
}
