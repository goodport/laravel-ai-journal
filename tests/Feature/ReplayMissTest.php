<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Tests\TestCase;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\PendingStep;
use Mockery;

final class ReplayMissTest extends TestCase
{
    public function test_replay_is_disabled_by_default(): void
    {
        $this->recordSourceStep('0190b4a0-0000-7000-8000-000000000081');

        $called = false;
        $middleware = $this->app->make(ReplayRecordedStep::class);

        $middleware->handle(
            $this->pendingStep('0190b4a0-0000-7000-8000-000000000082'),
            function () use (&$called): void {
                $called = true;
            },
        );

        $this->assertTrue($called);
    }

    public function test_step_with_tools_is_not_replayed(): void
    {
        $this->setJournalConfig('replay.ttl', 3600);
        $this->recordSourceStep('0190b4a0-0000-7000-8000-000000000083');

        $called = false;
        $tool = Mockery::mock(Tool::class);
        $middleware = $this->app->make(ReplayRecordedStep::class);

        $middleware->handle(
            $this->pendingStep('0190b4a0-0000-7000-8000-000000000084', tools: [$tool]),
            function (PendingStep $step) use (&$called): void {
                $called = true;
            },
        );

        $this->assertTrue($called);
    }

    private function recordSourceStep(string $invocationId): void
    {
        $prompt = $this->dispatchPrompting($invocationId);
        $this->dispatchCompletedStep($invocationId, $prompt);
        $this->dispatchPrompted($invocationId, $prompt);
    }
}
