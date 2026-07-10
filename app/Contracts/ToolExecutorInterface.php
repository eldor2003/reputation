<?php

namespace App\Contracts;

use App\DTO\ToolResultDTO;

interface ToolExecutorInterface
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolName, array $arguments): ToolResultDTO;
}
