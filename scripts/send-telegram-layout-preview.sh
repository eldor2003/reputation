#!/usr/bin/env bash
# Send Telegram card previews using real mention data from the database.
# Usage: ./scripts/send-telegram-layout-preview.sh [mention_id]
set -euo pipefail

cd /opt/reputation-project

MENTION_ID="${1:-${MENTION_ID:-}}"

docker compose exec -T -e "MENTION_ID=${MENTION_ID}" app php <<'PHP'
<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Contracts\DeliveryCardBuilderInterface;
use App\Contracts\DeliveryContextBuilderInterface;
use App\Contracts\TelegramDestinationNotifierInterface;
use App\Contracts\TelegramNotifierInterface;
use App\Enums\TelegramDestination;
use App\Models\AiResult;
use App\Models\Mention;
use App\Services\TelegramNotificationMessageBuilder;

$mentionIdArg = trim((string) (getenv('MENTION_ID') ?: ''));

$mentionQuery = Mention::query()
    ->with(['source', 'project', 'person'])
    ->whereNotNull('url')
    ->where('url', '!=', '');

if ($mentionIdArg !== '') {
    $mentionQuery->whereKey((int) $mentionIdArg);
} else {
    $mentionQuery->latest('id');
}

$mention = $mentionQuery->first();

if ($mention === null) {
    fwrite(STDERR, 'No mention with a URL found'.($mentionIdArg !== '' ? " for id {$mentionIdArg}" : '').".\n");
    exit(1);
}

$classification = AiResult::query()
    ->where('mention_id', $mention->id)
    ->latest('processed_at')
    ->first();

if ($classification === null) {
    fwrite(STDERR, "Mention #{$mention->id} has no AI classification.\n");
    exit(1);
}

echo "Using mention #{$mention->id}\n";
echo "URL: {$mention->url}\n\n";

$moderationPreview = $app->make(TelegramNotificationMessageBuilder::class)->build($mention, $classification);

$context = $app->make(DeliveryContextBuilderInterface::class)->buildForMention($mention->id);
$card = $app->make(DeliveryCardBuilderInterface::class)->build($context);
$deliveryPreview = $app->make(DeliveryCardBuilderInterface::class)->formatCard($card);

$moderation = $app->make(TelegramNotifierInterface::class);
$delivery = $app->make(TelegramDestinationNotifierInterface::class);

foreach (config('telegram.chat_ids', []) as $chatId) {
    $result = $moderation->send((string) $chatId, $moderationPreview);
    echo "Moderation preview sent to {$chatId}: message {$result->messageId}\n";
}

foreach (config('delivery.telegram.telegram_delivery.chat_ids', []) as $chatId) {
    $result = $delivery->send(TelegramDestination::Delivery, (string) $chatId, $deliveryPreview);
    echo "Delivery preview sent to {$chatId}: message {$result->messageId}\n";
}

echo "\n--- Moderation preview ---\n{$moderationPreview}\n";
echo "\n--- Delivery preview ---\n{$deliveryPreview}\n";
PHP
