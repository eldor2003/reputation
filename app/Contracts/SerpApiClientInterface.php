<?php

namespace App\Contracts;

use App\DTO\SerpApiAccountInfoDTO;
use App\DTO\SerpSearchRequestDTO;
use App\DTO\SerpSearchResultDTO;

interface SerpApiClientInterface
{
    public function testConnection(): SerpApiAccountInfoDTO;

    public function getAccountInfo(): SerpApiAccountInfoDTO;

    public function search(SerpSearchRequestDTO $request): SerpSearchResultDTO;
}
