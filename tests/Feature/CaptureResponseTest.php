<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiJournal\Tests\TestCase;

final class CaptureResponseTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.replay.ttl', 3600);
        $app['config']->set('ai-journal.capture.response', false);
    }

    public function test_disabled_response_capture_prevents_storage_and_replay(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000111';
        $prompt = $this->dispatchPrompting($invocationId);
        $this->dispatchCompletedStep($invocationId, $prompt);
        $this->dispatchPrompted($invocationId, $prompt);

        $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();
        $step = AiInvocationStep::query()->where('ai_invocation_id', $invocation->id)->firstOrFail();

        $this->assertNull($invocation->response_text);
        $this->assertNull($step->response);

        $called = false;
        $middleware = $this->app->make(ReplayRecordedStep::class);

        $middleware->handle(
            $this->pendingStep('0190b4a0-0000-7000-8000-000000000112'),
            function () use (&$called): void {
                $called = true;
            },
        );

        $this->assertTrue($called);
    }
}
