<?php

namespace App\Services\Classification;

use App\Contracts\PromptInjectionGuardInterface;
use App\DTO\NormalizedMentionDTO;
use App\DTO\PromptGuardResultDTO;
use App\DTO\ToolResultDTO;

class PromptInjectionGuard implements PromptInjectionGuardInterface
{
    public function scan(NormalizedMentionDTO $mention): PromptGuardResultDTO
    {
        if (! (bool) config('classification.injection_guard.enabled', true)) {
            return new PromptGuardResultDTO(injectionDetected: false, reason: null);
        }

        $haystack = mb_strtolower(implode("\n", array_filter([
            $mention->title,
            $mention->author,
            $mention->text,
        ])));

        $patterns = config('classification.injection_guard.patterns', []);

        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            if (preg_match($pattern, $haystack) === 1) {
                return new PromptGuardResultDTO(
                    injectionDetected: true,
                    reason: "Matched injection pattern: {$pattern}",
                );
            }
        }

        return new PromptGuardResultDTO(injectionDetected: false, reason: null);
    }
}
