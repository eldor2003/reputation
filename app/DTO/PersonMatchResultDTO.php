<?php

namespace App\DTO;

readonly class PersonMatchResultDTO
{
    /**
     * @param  list<PersonMatchCandidateDTO>  $candidates
     */
    public function __construct(
        public ?ResolvedPersonDTO $resolvedPerson,
        public bool $isAmbiguous,
        public array $candidates,
    ) {}
}
