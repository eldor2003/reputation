<?php

namespace App\Enums;

enum LlmCascadeTier: string
{
    case Haiku = 'haiku';
    case Sonnet = 'sonnet';
    case Opus = 'opus';
}
