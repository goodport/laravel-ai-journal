<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Tests\TestCase;
use Illuminate\Support\Facades\Context;

final class ReplayFreshTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.replay.ttl', 3600);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();

        parent::tearDown();
    }

    public function test_fresh_context_bypasses_replay(): void
    {
        $sourceId = '0190b4a0-0000-7000-8000-000000000141';
        $prompt = $this->dispatchPrompting($sourceId);
        $this->dispatchCompletedStep($sourceId, $prompt);
        $this->dispatchPrompted($sourceId, $prompt);

        Context::add('ai.journal.fresh', true);

        $called = false;
        $middleware = $this->app->make(ReplayRecordedStep::class);

        $middleware->handle(
            $this->pendingStep('0190b4a0-0000-7000-8000-000000000142'),
            function () use (&$called): void {
                $called = true;
            },
        );

        $this->assertTrue($called);
    }
}
