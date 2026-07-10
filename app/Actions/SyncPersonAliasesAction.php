<?php

namespace App\Actions;

use App\Models\Person;
use App\Services\PersonService;

class SyncPersonAliasesAction
{
    public function __construct(
        private readonly PersonService $personService,
    ) {}

    public function execute(string $uuid): Person
    {
        return $this->personService->syncPersonAliases($uuid);
    }
}
