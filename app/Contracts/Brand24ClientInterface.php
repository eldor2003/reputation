<?php

namespace App\Contracts;

use App\DTO\Brand24AccountInfoDTO;
use App\DTO\Brand24MentionsPageDTO;
use App\DTO\Brand24MentionsQueryDTO;
use App\DTO\Brand24ProjectsListDTO;

interface Brand24ClientInterface
{
    public function testConnection(): Brand24AccountInfoDTO;

    public function getProjects(int $accountId): Brand24ProjectsListDTO;

    public function getMentions(Brand24MentionsQueryDTO $query): Brand24MentionsPageDTO;
}
