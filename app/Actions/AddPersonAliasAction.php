<?php

namespace App\Actions;

use App\DTO\PersonAliasInputDTO;
use App\Models\PersonAlias;
use App\Services\PersonService;

class AddPersonAliasAction
{
    public function __construct(
        private readonly PersonService $personService,
    ) {}

    public function execute(string $personUuid, PersonAliasInputDTO $input): PersonAlias
    {
        return $this->personService->addCustomAlias($personUuid, $input);
    }
}
