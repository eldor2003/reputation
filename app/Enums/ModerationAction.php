<?php

namespace App\Enums;

enum ModerationAction: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Skip = 'skip';
}
