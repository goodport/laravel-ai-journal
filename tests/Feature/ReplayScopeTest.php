<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Tests\TestCase;
use Illuminate\Support\Facades\Context;

final class ReplayScopeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.replay.ttl', 3600);
    }

    protected function tearDown(): void
    {
        Context::flush();

        parent::tearDown();
    }

    public function test_replay_does_not_cross_scopes(): void
    {
        Context::add('ai.journal.scope', 'tenant-a');

        $sourceId = '0190b4a0-0000-7000-8000-000000000131';
        $prompt = $this->dispatchPrompting($sourceId);
        $this->dispatchCompletedStep($sourceId, $prompt);
        $this->dispatchPrompted($sourceId, $prompt);

        Context::forget('ai.journal.scope');
        Context::add('ai.journal.scope', 'tenant-b');

        $called = false;
        $middleware = $this->app->make(ReplayRecordedStep::class);

        $middleware->handle(
            $this->pendingStep('0190b4a0-0000-7000-8000-000000000132'),
            function () use (&$called): void {
                $called = true;
            },
        );

        $this->assertTrue($called);
    }
}
