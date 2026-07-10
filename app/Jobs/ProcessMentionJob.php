<?php

namespace App\Jobs;

use App\Actions\ProcessMentionAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMentionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $mentionId,
    ) {}

    public function handle(ProcessMentionAction $action): void
    {
        $action->execute($this->mentionId);
    }
}
