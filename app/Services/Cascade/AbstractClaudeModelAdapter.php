<?php

namespace App\Services\Cascade;

use App\Contracts\ClaudeClientInterface;
use App\Contracts\LLMModelInterface;
use App\Enums\LlmCascadeTier;

abstract class AbstractClaudeModelAdapter implements LLMModelInterface
{
    public function __construct(
        protected readonly ClaudeClientInterface $client,
    ) {}

    abstract protected function configKey(): string;

    public function tier(): string
    {
        return $this->configKey();
    }

    public function modelName(): string
    {
        return (string) config("cascade.models.{$this->configKey()}.name");
    }

    public function send(string $prompt): array
    {
        return $this->client->send($prompt, $this->modelName());
    }
}
