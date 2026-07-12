<?php

namespace App\Services\Telegram;

use App\Enums\SourceType;
use App\Enums\ThreatLevel;
use App\Models\Mention;
use App\Models\Person;
use App\Models\Source;
use Carbon\CarbonInterface;

class TelegramCardMessageLayout
{
    public const SEPARATOR = '━━━━━━━━━━━━━━━━━━━━';

    public const CONFIRMATION_SEPARATOR = '____________________';

    private const LINE = self::SEPARATOR;

    public function format(
        string $sourceLabel,
        ?string $sentiment,
        ?string $threatLevel,
        int $severity,
        ?string $person,
        ?string $category,
        ?string $language,
        ?int $confidence,
        ?string $summary,
        ?string $url,
        ?CarbonInterface $occurredAt,
        int $mentionId,
        ?string $projectName,
        ?CarbonInterface $approvedAt = null,
    ): string {
        $lines = [
            '🌐 '.$this->cleanDisplayValue($sourceLabel, 'Web'),
            self::LINE,
        ];

        $sentimentLine = $this->formatSentimentLine($sentiment);
        if ($sentimentLine !== null) {
            $lines[] = $sentimentLine;
        }

        $threatLines = $this->formatThreatSection($threatLevel, $severity);
        if ($threatLines !== []) {
            $lines = array_merge($lines, $threatLines);
        }

        $lines[] = self::SEPARATOR;

        foreach ($this->formatMetaLines($person, $category, $language, $confidence) as $metaLine) {
            $lines[] = $metaLine;
        }

        $summaryBlock = $this->formatSummaryBlock($summary);
        if ($summaryBlock !== []) {
            if ($this->hasMetaContent($person, $category, $language, $confidence)) {
                $lines[] = self::SEPARATOR;
            }

            $lines = array_merge($lines, $summaryBlock);
        }

        $urlBlock = $this->formatUrlBlock($url);
        if ($urlBlock !== []) {
            $lines[] = self::SEPARATOR;
            $lines = array_merge($lines, $urlBlock);
        }

        $footer = $this->formatFooter($occurredAt, $mentionId, $projectName);
        if ($footer !== []) {
            $lines[] = self::SEPARATOR;
            $lines = array_merge($lines, $footer);
        }

        if ($approvedAt !== null) {
            $lines[] = self::CONFIRMATION_SEPARATOR;
            $lines[] = '✓ Подтверждено · '.$this->formatFooterDateTime($approvedAt);
        }

        return implode("\n", $lines);
    }

    public function resolveSourceLabel(Mention $mention, ?Source $source, ?string $fallback = null): string
    {
        /** @var array<string, mixed>|null $metadata */
        $metadata = is_array($mention->metadata) ? $mention->metadata : null;

        if ($metadata !== null) {
            foreach (['mchannel', 'channel_name', 'platform', 'site_type', 'page_type', 'host'] as $key) {
                $value = $metadata[$key] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    return $this->normalizePlatformLabel($value);
                }
            }
        }

        $platformFromUrl = $this->resolvePlatformFromUrl($mention->url);

        if ($platformFromUrl !== null) {
            return $platformFromUrl;
        }

        if ($fallback !== null && trim($fallback) !== '') {
            return $this->normalizePlatformLabel($fallback);
        }

        if ($source !== null) {
            if (is_string($source->name) && trim($source->name) !== '') {
                return $this->normalizePlatformLabel($source->name);
            }

            return $this->formatSourceType($source->type);
        }

        return 'Web';
    }

    public function resolveDisplayPerson(Mention $mention, ?string $aiPerson): ?string
    {
        if ($mention->relationLoaded('person') === false) {
            $mention->loadMissing('person');
        }

        if ($this->isPresent($mention->person?->full_name)) {
            return $mention->person->full_name;
        }

        $projectPersonNames = Person::query()
            ->where('project_id', $mention->project_id)
            ->where('is_active', true)
            ->pluck('full_name')
            ->filter(fn (?string $name): bool => $this->isPresent($name))
            ->unique()
            ->values();

        if ($projectPersonNames->count() === 1) {
            return (string) $projectPersonNames->first();
        }

        return $this->isPresent($aiPerson) ? $aiPerson : null;
    }

    /**
     * @return list<string>
     */
    private function formatThreatSection(?string $threatLevel, int $severity): array
    {
        if ($threatLevel === null || trim($threatLevel) === '') {
            return [];
        }

        $normalizedLevel = strtoupper(trim($threatLevel));
        $emoji = $this->threatEmoji($normalizedLevel);
        $label = $this->threatLabel($normalizedLevel);

        return array_filter([
            trim($emoji.' '.$normalizedLevel),
            $label,
            $this->severityBar($severity),
        ], fn (?string $line): bool => is_string($line) && $line !== '');
    }

    private function formatSentimentLine(?string $sentiment): ?string
    {
        if ($sentiment === null || trim($sentiment) === '') {
            return null;
        }

        $emoji = match (strtolower(trim($sentiment))) {
            'positive' => '🙂',
            'neutral' => '😐',
            'negative' => '☹️',
            default => null,
        };

        if ($emoji === null) {
            return null;
        }

        return $emoji.' '.$this->translateSentiment($sentiment);
    }

    /**
     * @return list<string>
     */
    private function formatMetaLines(
        ?string $person,
        ?string $category,
        ?string $language,
        ?int $confidence,
    ): array {
        $lines = [];

        if ($this->isPresent($person)) {
            $lines[] = '👤 '.$this->cleanDisplayValue($person);
        }

        if ($this->isPresent($category)) {
            $lines[] = '📂 '.$this->cleanDisplayValue($category);
        }

        if ($this->isPresent($language)) {
            $lines[] = '🌍 '.$this->cleanDisplayValue($language);
        }

        if ($confidence !== null && $confidence > 0) {
            $lines[] = '🎯 '.$confidence.'%';
        }

        return $lines;
    }

    private function hasMetaContent(?string $person, ?string $category, ?string $language, ?int $confidence): bool
    {
        return $this->isPresent($person)
            || $this->isPresent($category)
            || $this->isPresent($language)
            || ($confidence !== null && $confidence > 0);
    }

    /**
     * @return list<string>
     */
    private function formatSummaryBlock(?string $summary): array
    {
        if (! $this->isPresent($summary)) {
            return [];
        }

        $wrapped = $this->wrapSummary((string) $summary);

        if ($wrapped === '') {
            return [];
        }

        return array_merge(['📝 Summary', ''], explode("\n", $wrapped));
    }

    /**
     * @return list<string>
     */
    private function formatUrlBlock(?string $url): array
    {
        if (! $this->isPresent($url)) {
            return [];
        }

        return ['🔗 URL', '', trim((string) $url)];
    }

    /**
     * @return list<string>
     */
    private function formatFooter(?CarbonInterface $occurredAt, int $mentionId, ?string $projectName): array
    {
        $lines = [];

        if ($occurredAt !== null) {
            $lines[] = '⏱️ '.$this->formatFooterDateTime($occurredAt);
        }

        if ($mentionId > 0) {
            $lines[] = '#M-'.$mentionId;
        }

        if ($this->isPresent($projectName)) {
            $lines[] = 'Проект: '.$this->cleanDisplayValue($projectName);
        }

        return $lines;
    }

    private function severityBar(int $severity): string
    {
        $filled = max(0, min(5, $severity));

        return str_repeat('●', $filled).str_repeat('○', 5 - $filled);
    }

    private function threatEmoji(string $level): string
    {
        return match ($level) {
            ThreatLevel::P1->value => '🔴',
            ThreatLevel::P2->value => '🟠',
            ThreatLevel::P3->value => '🟡',
            ThreatLevel::P4->value => '🟢',
            default => '⚪',
        };
    }

    private function threatLabel(string $level): string
    {
        return match ($level) {
            ThreatLevel::P1->value => 'Критическая угроза',
            ThreatLevel::P2->value => 'Высокая угроза',
            ThreatLevel::P3->value => 'Средняя угроза',
            ThreatLevel::P4->value => 'Низкая угроза',
            default => 'Уровень угрозы',
        };
    }

    private function translateSentiment(string $sentiment): string
    {
        return match (strtolower(trim($sentiment))) {
            'negative' => 'Негатив',
            'neutral' => 'Нейтрал',
            'positive' => 'Позитив',
            default => ucfirst(trim($sentiment)),
        };
    }

    private function normalizePlatformLabel(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match (true) {
            str_contains($normalized, 'youtube') => 'YouTube',
            str_contains($normalized, 'telegram') => 'Telegram',
            str_contains($normalized, 'twitter') || $normalized === 'x' => 'X',
            str_contains($normalized, 'news') => 'News',
            str_contains($normalized, 'serp') => 'SERP',
            str_contains($normalized, 'facebook') => 'Facebook',
            str_contains($normalized, 'instagram') => 'Instagram',
            str_contains($normalized, 'tiktok') => 'TikTok',
            str_contains($normalized, 'reddit') => 'Reddit',
            str_contains($normalized, 'linkedin') => 'LinkedIn',
            default => ucfirst(trim($value)),
        };
    }

    private function formatSourceType(SourceType $type): string
    {
        return match ($type) {
            SourceType::Brand24 => 'Brand24',
            SourceType::Mentionlytics => 'Mentionlytics',
            SourceType::YouScan => 'YouScan',
        };
    }

    private function resolvePlatformFromUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        return match (true) {
            str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be') => 'YouTube',
            str_contains($host, 't.me') || str_contains($host, 'telegram.') => 'Telegram',
            str_contains($host, 'twitter.com') || str_contains($host, 'x.com') => 'X',
            str_contains($host, 'news.') => 'News',
            default => null,
        };
    }

    private function wrapSummary(string $summary): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($summary)) ?? trim($summary);

        if ($normalized === '') {
            return '';
        }

        $wrapped = wordwrap($normalized, 60, "\n", true);
        $lines = explode("\n", $wrapped);

        return implode("\n", array_slice($lines, 0, 5));
    }

    private function formatFooterDateTime(CarbonInterface $dateTime): string
    {
        $timezone = (string) config('app.timezone', 'UTC');

        return $dateTime->copy()->timezone($timezone)->format('d.m H:i');
    }

    private function isPresent(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return false;
        }

        return ! in_array(mb_strtolower($trimmed), [
            'unknown',
            'неизвестно',
            'n/a',
            'другое',
            'other',
        ], true);
    }

    private function cleanDisplayValue(?string $value, ?string $fallback = null): string
    {
        if ($this->isPresent($value)) {
            return trim((string) $value);
        }

        return $fallback ?? '';
    }
}
