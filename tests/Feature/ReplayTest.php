<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiJournal\Tests\TestCase;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use RuntimeException;

final class ReplayTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.replay.ttl', 3600);
    }

    public function test_successful_step_is_replayed_without_provider_call(): void
    {
        $sourceInvocationId = '0190b4a0-0000-7000-8000-000000000011';
        $prompt = $this->dispatchPrompting($sourceInvocationId);
        $this->dispatchCompletedStep($sourceInvocationId, $prompt);
        $this->dispatchPrompted($sourceInvocationId, $prompt);

        $sourceStep = AiInvocationStep::query()->firstOrFail();
        $agent = new AnonymousAgent('Instructions', [], []);
        $pending = new PendingStep(
            number: 0,
            isFinalStep: true,
            provider: 'openai',
            model: 'gpt-test',
            instructions: 'Instructions',
            messages: [new UserMessage('Hello')],
            tools: [],
            schema: null,
            options: TextGenerationOptions::forAgent($agent),
            invocationId: '0190b4a0-0000-7000-8000-000000000012',
        );

        $middleware = $this->app->make(ReplayRecordedStep::class);

        $result = $middleware->handle($pending, fn (): never => throw new RuntimeException('Provider must not be called.'));

        $this->assertInstanceOf(StepResponse::class, $result);
        $this->assertSame('OK', $result->text);

        $replayStep = AiInvocationStep::query()
            ->where('request_fingerprint', $sourceStep->request_fingerprint)
            ->where('status', StepStatus::Replayed->value)
            ->firstOrFail();

        $this->assertSame($sourceStep->id, $replayStep->replay_of_step_id);
        $this->assertEquals(0, $replayStep->cost);
    }
}
