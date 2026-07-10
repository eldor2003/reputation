<?php

namespace App\Contracts;

use App\DTO\NormalizedMentionDTO;
use App\DTO\PersonMatchResultDTO;

interface PersonResolverInterface
{
    public function resolve(NormalizedMentionDTO $mention): PersonMatchResultDTO;
}
