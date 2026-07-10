<?php

namespace App\Actions;

use App\DTO\CreatePersonData;
use App\Models\Person;
use App\Services\PersonService;

class CreatePersonAction
{
    public function __construct(
        private readonly PersonService $personService,
    ) {}

    public function execute(CreatePersonData $data): Person
    {
        return $this->personService->createPerson($data);
    }
}
