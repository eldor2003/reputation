<?php

namespace App\DTO;

use App\Models\SerpSnapshot;

readonly class RankingHistoryResultDTO
{
    /**
     * @param  list<SerpSnapshot>  $snapshots
     * @param  list<RankingPositionPointDTO>  $positionHistory
     * @param  list<RankingDeltaDTO>  $rankingDeltas
     */
    public function __construct(
        public array $snapshots,
        public array $positionHistory,
        public array $rankingDeltas,
    ) {}
}
