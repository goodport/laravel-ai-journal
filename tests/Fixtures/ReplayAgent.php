<?php

namespace Goodport\AiJournal\Tests\Fixtures;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasMiddleware;

final class ReplayAgent extends AnonymousAgent implements HasMiddleware
{
    public function middleware(): array
    {
        return [
            ReplayRecordedStep::class,
        ];
    }
}
