<?php

namespace App\Contracts;

use App\DTO\MentionSimilarityScoreDTO;
use App\DTO\NormalizedMentionDTO;

interface SimilarityCalculatorInterface
{
    public function score(NormalizedMentionDTO $left, NormalizedMentionDTO $right): MentionSimilarityScoreDTO;

    public function isDuplicate(MentionSimilarityScoreDTO $score): bool;
}
