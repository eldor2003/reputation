<?php

namespace App\Listeners;

use App\Actions\QueueRoutedMentionForDigestAction;
use App\Events\MentionRouted;
use Illuminate\Support\Facades\Log;

class QueueRoutedMentionForDigestListener
{
    public function __construct(
        private readonly QueueRoutedMentionForDigestAction $queueRoutedMentionForDigestAction,
    ) {}

    public function handle(MentionRouted $event): void
    {
        $result = $this->queueRoutedMentionForDigestAction->execute($event->mentionId);

        if ($result?->queuedForDigest) {
            Log::info('Routed mention queued for digest.', [
                'mention_id' => $event->mentionId,
                'project_id' => $event->projectId,
            ]);
        }
    }
}
