<?php

namespace App\Enums;

enum SourceType: string
{
    case YouScan = 'youscan';
    case Brand24 = 'brand24';
    case Mentionlytics = 'mentionlytics';
}
