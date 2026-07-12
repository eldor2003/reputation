<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryCardBuilderInterface;
use App\DTO\DeliveryCardDTO;
use App\DTO\DeliveryContextDTO;
use App\Enums\ModerationAction;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\ModerationLog;
use App\Models\Project;
use App\Services\Telegram\TelegramCardMessageLayout;

class DeliveryCardBuilder implements DeliveryCardBuilderInterface
{
    public function __construct(
        private readonly TelegramCardMessageLayout $layout,
    ) {}

    public function build(DeliveryContextDTO $context): DeliveryCardDTO
    {
        $personName = $this->layout->resolveDisplayPerson(
            $context->mention,
            $context->aiResult->person,
        ) ?? 'неизвестно';

        return new DeliveryCardDTO(
            mentionId: $context->mention->id,
            projectId: $context->mention->project_id,
            person: $personName,
            threatLevel: $context->threatResult->threat_level->value,
            threatScore: $context->threatResult->threat_score,
            source: $context->source->name ?? $context->source->type->value,
            summary: $context->aiResult->summary ?? '',
            url: $context->mention->url,
            sentiment: $context->aiResult->sentiment ?? 'unknown',
            severity: (int) ($context->aiResult->severity ?? 0),
            serpPosition: $context->serpPosition,
            clusterSize: $context->clusterSize,
            publishedAt: $context->mention->published_at,
            processedAt: $context->timestamp,
        );
    }

    public function formatCard(DeliveryCardDTO $card): string
    {
        $mention = Mention::query()->with(['source', 'person'])->find($card->mentionId);
        $aiResult = AiResult::query()
            ->where('mention_id', $card->mentionId)
            ->latest('processed_at')
            ->first();
        $projectName = Project::query()->find($card->projectId)?->name;
        $approvedAt = ModerationLog::query()
            ->where('mention_id', $card->mentionId)
            ->where('action', ModerationAction::Approve)
            ->latest('created_at')
            ->first()
            ?->created_at;
        $mentionTime = $mention?->published_at ?? $mention?->received_at ?? $card->publishedAt;
        $mentionForSource = $mention ?? new Mention([
            'url' => $card->url,
            'metadata' => $this->sourceMetadataFromLabel($card->source),
        ]);

        return $this->layout->format(
            sourceLabel: $this->layout->resolveSourceLabel(
                mention: $mentionForSource,
                source: $mention?->source,
                fallback: $card->source,
            ),
            sentiment: $card->sentiment,
            threatLevel: $card->threatLevel,
            severity: $card->severity,
            person: $mention !== null
                ? $this->layout->resolveDisplayPerson($mention, $aiResult?->person ?? $card->person)
                : ($this->layout->resolveDisplayPerson(new Mention(['project_id' => $card->projectId]), $card->person) ?? $card->person),
            category: $aiResult?->category,
            language: $aiResult?->language,
            confidence: $aiResult?->confidence !== null ? (int) $aiResult->confidence : null,
            summary: $card->summary,
            url: $card->url,
            occurredAt: $mentionTime,
            mentionId: $card->mentionId,
            projectName: $projectName,
            approvedAt: $approvedAt,
        );
    }

    /**
     * @param  list<DeliveryCardDTO>  $cards
     */
    public function formatDigest(string $title, array $cards): string
    {
        $sections = [
            '📬 '.$title,
            '',
            'Всего упоминаний: '.count($cards),
            '',
            TelegramCardMessageLayout::SEPARATOR,
            '',
        ];

        foreach ($cards as $index => $card) {
            $sections[] = '#'.($index + 1);
            $sections[] = '';
            $sections[] = $this->formatCard($card);
            $sections[] = '';
        }

        $sections[] = TelegramCardMessageLayout::SEPARATOR;

        return implode("\n", $sections);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceMetadataFromLabel(string $source): array
    {
        return ['platform' => $source];
    }
}
