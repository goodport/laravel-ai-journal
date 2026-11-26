<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Tests\TestCase;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Gateway\StepResponse;

final class ReplayTtlContextTest extends TestCase
{
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

    public function test_context_ttl_enables_replay_when_config_is_disabled(): void
    {
        $sourceId = '0190b4a0-0000-7000-8000-000000000151';
        $prompt = $this->dispatchPrompting($sourceId);
        $this->dispatchCompletedStep($sourceId, $prompt);
        $this->dispatchPrompted($sourceId, $prompt);

        Context::add('ai.journal.ttl', 3600);

        $result = $this->app->make(ReplayRecordedStep::class)
            ->handle(
                $this->pendingStep('0190b4a0-0000-7000-8000-000000000152'),
                fn (): never => throw new \RuntimeException('Provider must not be called.'),
            );

        $this->assertInstanceOf(StepResponse::class, $result);
        $this->assertSame('OK', $result->text);
    }
}
