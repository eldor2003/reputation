<?php

namespace App\DTO;

use App\Enums\SerpEngine;

readonly class SerpSearchRequestDTO
{
    public function __construct(
        public string $query,
        public SerpEngine $engine,
        public ?string $location = null,
        public ?string $language = null,
        public ?int $num = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toQueryParameters(): array
    {
        $parameters = [
            'engine' => $this->engine->serpApiEngine(),
        ];

        $parameters[$this->queryParameterName()] = $this->query;

        if ($this->location !== null) {
            $parameters['location'] = $this->location;
        }

        if ($this->language !== null) {
            $parameters['hl'] = $this->language;
        }

        if ($this->num !== null) {
            $parameters['num'] = $this->num;
        }

        return $parameters;
    }

    private function queryParameterName(): string
    {
        return match ($this->engine) {
            SerpEngine::Yandex => 'text',
            default => 'q',
        };
    }
}
