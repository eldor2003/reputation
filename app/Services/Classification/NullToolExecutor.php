<?php

namespace App\Services\Classification;

use App\Contracts\ToolExecutorInterface;
use App\DTO\ToolResultDTO;

class NullToolExecutor implements ToolExecutorInterface
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolName, array $arguments): ToolResultDTO
    {
        return new ToolResultDTO(
            toolName: $toolName,
            success: false,
            payload: [],
            error: 'Tool use is not enabled.',
        );
    }
}
