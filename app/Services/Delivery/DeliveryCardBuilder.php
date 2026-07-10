<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryCardBuilderInterface;
use App\DTO\DeliveryCardDTO;
use App\DTO\DeliveryContextDTO;
use Carbon\CarbonInterface;

class DeliveryCardBuilder implements DeliveryCardBuilderInterface
{
    public function build(DeliveryContextDTO $context): DeliveryCardDTO
    {
        $personName = $context->person?->full_name
            ?? $context->aiResult->person
            ?? 'неизвестно';

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
        $lines = [
            '————————————',
            '',
            '📦 Карточка доставки',
            '',
            '👤 Персона:',
            $card->person,
            '',
            '🎯 Уровень угрозы:',
            $card->threatLevel.' ('.$card->threatScore.')',
            '',
            '📡 Источник:',
            $card->source,
            '',
            '📝 Краткое содержание:',
            '',
            $card->summary,
            '',
            '🔗 URL:',
            '',
            $card->url ?? 'не указан',
            '',
            '😊 Тональность:',
            $this->translateSentiment($card->sentiment),
            '',
            '⚠ Критичность:',
            $card->severity.' / 5',
            '',
            '🔍 SERP позиция:',
            $card->serpPosition !== null ? (string) $card->serpPosition : 'н/д',
            '',
            '📊 Размер кластера:',
            (string) $card->clusterSize,
            '',
            '🕒 Publication Date:',
            $this->formatPublicationDate($card->publishedAt),
            '',
            '⚙️ Processed At:',
            $this->formatProcessedAt($card->processedAt),
            '',
            '————————————',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  list<DeliveryCardDTO>  $cards
     */
    public function formatDigest(string $title, array $cards): string
    {
        $sections = [
            '========================',
            '',
            '📬 '.$title,
            '',
            'Всего упоминаний: '.count($cards),
            '',
        ];

        foreach ($cards as $index => $card) {
            $sections[] = '#'.($index + 1);
            $sections[] = '';
            $sections[] = $this->formatCard($card);
            $sections[] = '';
        }

        $sections[] = '========================';

        return implode("\n", $sections);
    }

    private function formatPublicationDate(?CarbonInterface $publishedAt): string
    {
        if ($publishedAt === null) {
            return 'Unknown';
        }

        return $this->formatDateTime($publishedAt);
    }

    private function formatProcessedAt(CarbonInterface $processedAt): string
    {
        return $this->formatDateTime($processedAt);
    }

    private function formatDateTime(CarbonInterface $dateTime): string
    {
        $timezone = (string) config('app.timezone', 'UTC');

        return $dateTime->copy()->timezone($timezone)->format('d.m.Y H:i T');
    }

    private function translateSentiment(string $sentiment): string
    {
        return match ($sentiment) {
            'negative' => 'негативная',
            'neutral' => 'нейтральная',
            'positive' => 'позитивная',
            default => 'неизвестно',
        };
    }
}
