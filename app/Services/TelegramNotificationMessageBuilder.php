<?php

namespace App\Services;

use App\Models\AiResult;
use App\Models\Mention;
use App\Services\Telegram\TelegramCardMessageLayout;

class TelegramNotificationMessageBuilder
{
    public function __construct(
        private readonly TelegramCardMessageLayout $layout,
    ) {}

    public function build(Mention $mention, AiResult $classification): string
    {
        $mention->loadMissing(['source', 'project', 'person', 'latestThreatResult']);

        $threatLevel = $mention->latestThreatResult?->threat_level?->value;
        $mentionTime = $mention->published_at ?? $mention->received_at;
        $personName = $this->layout->resolveDisplayPerson($mention, $classification->person);

        return $this->layout->format(
            sourceLabel: $this->layout->resolveSourceLabel($mention, $mention->source),
            sentiment: $classification->sentiment,
            threatLevel: $threatLevel,
            severity: (int) ($classification->severity ?? 0),
            person: $personName,
            category: $classification->category,
            language: $classification->language,
            confidence: $classification->confidence !== null ? (int) $classification->confidence : null,
            summary: $classification->summary,
            url: $mention->url,
            occurredAt: $mentionTime,
            mentionId: $mention->id,
            projectName: $mention->project?->name,
        );
    }
}
