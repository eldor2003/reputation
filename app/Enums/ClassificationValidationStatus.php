<?php

namespace App\Enums;

enum ClassificationValidationStatus: string
{
    case Valid = 'valid';
    case Retry = 'retry';
    case Escalated = 'escalated';
    case Invalid = 'invalid';
}
