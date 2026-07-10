<?php

namespace App\Actions;

use App\DTO\UpdatePersonData;
use App\Models\Person;
use App\Services\PersonService;

class UpdatePersonAction
{
    public function __construct(
        private readonly PersonService $personService,
    ) {}

    public function execute(string $uuid, UpdatePersonData $data): Person
    {
        return $this->personService->updatePerson($uuid, $data);
    }
}
