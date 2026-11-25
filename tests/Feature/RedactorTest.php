<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Tests\Fixtures\SecretRedactor;
use Goodport\AiJournal\Tests\TestCase;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;

final class RedactorTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.replay.ttl', 3600);
        $app['config']->set('ai-journal.redactor', SecretRedactor::class);
    }

    public function test_stored_text_is_redacted_and_replay_is_disabled(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000101';
        $agent = new AnonymousAgent('Instructions', [], []);
        $provider = $this->provider();
        $prompt = new AgentPrompt($agent, 'secret prompt', [], $provider, 'gpt-test', invocationId: $invocationId);
        $options = TextGenerationOptions::forAgent($agent);

        event(new PromptingAgent($invocationId, $prompt));
        event(new StartingStep($invocationId, 0, $agent, $provider, 'gpt-test', true, [new UserMessage('secret message')], $options));
        event(new StepCompleted(
            $invocationId,
            0,
            $agent,
            $provider,
            'gpt-test',
            true,
            new StepResponse(
                text: 'secret answer',
                toolCalls: [],
                finishReason: FinishReason::Stop,
                usage: new TextUsage(10, 5),
                meta: new Meta('openai', 'gpt-test'),
            ),
            10.0,
        ));
        event(new AgentPrompted($invocationId, $prompt, new AgentResponse(
            $invocationId,
            'secret answer',
            new TextUsage(10, 5),
            new Meta('openai', 'gpt-test'),
        )));

        $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();

        $this->assertSame('[redacted] prompt', $invocation->prompt);
        $this->assertSame('[redacted] answer', $invocation->response_text);

        $called = false;
        $middleware = $this->app->make(ReplayRecordedStep::class);

        $middleware->handle(
            $this->pendingStep('0190b4a0-0000-7000-8000-000000000102'),
            function () use (&$called): void {
                $called = true;
            },
        );

        $this->assertTrue($called);
    }
}
