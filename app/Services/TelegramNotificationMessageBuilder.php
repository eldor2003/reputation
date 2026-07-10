<?php

namespace App\Services;

use App\Models\AiResult;
use App\Models\Mention;

class TelegramNotificationMessageBuilder
{
    public function build(Mention $mention, AiResult $classification): string
    {
        $url = $mention->url ?? 'N/A';

        return implode("\n", [
            '————————————',
            '',
            '🚨 Оповещение о репутации',
            '',
            '👤 Персона:',
            $classification->person ?? 'неизвестно',
            '',
            '📂 Категория:',
            $classification->category ?? 'другое',
            '',
            '😊 Тональность:',
            $this->translateSentiment($classification->sentiment),
            '',
            '⚠ Критичность:',
            ($classification->severity ?? 0).' / 5',
            '',
            '🌍 Язык:',
            $classification->language ?? 'неизвестно',
            '',
            '📈 Уверенность:',
            ($classification->confidence ?? 0).'%',
            '',
            '📝 Краткое содержание:',
            '',
            $classification->summary ?? '',
            '',
            '🔗 URL:',
            '',
            $url === 'N/A' ? 'не указан' : $url,
            '',
            '————————————',
        ]);
    }

    private function translateSentiment(?string $sentiment): string
    {
        return match ($sentiment) {
            'negative' => 'негативная',
            'neutral' => 'нейтральная',
            'positive' => 'позитивная',
            default => 'неизвестно',
        };
    }
}
