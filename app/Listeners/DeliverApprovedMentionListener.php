<?php

namespace App\Listeners;

use App\Actions\DeliverApprovedMentionAction;
use App\Events\MentionApproved;
use App\Exceptions\DeliveryConfigurationException;
use Illuminate\Support\Facades\Log;

class DeliverApprovedMentionListener
{
    public function __construct(
        private readonly DeliverApprovedMentionAction $deliverApprovedMentionAction,
    ) {}

    public function handle(MentionApproved $event): void
    {
        try {
            $result = $this->deliverApprovedMentionAction->execute($event->mentionId);

            if (! $result->success) {
                Log::error('Delivery failed after mention approval.', [
                    'mention_id' => $event->mentionId,
                    'error' => $result->errorMessage,
                ]);
            }
        } catch (DeliveryConfigurationException $exception) {
            Log::error('Delivery configuration error after mention approval.', [
                'mention_id' => $event->mentionId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
