<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Tests\Fixtures\StructuredAgent;
use Goodport\AiJournal\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class StructuredReplayTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.replay.ttl', 3600);
    }

    public function test_structured_response_is_replayed(): void
    {
        $sourceId = '0190b4a0-0000-7000-8000-000000000091';
        $agent = new StructuredAgent('Instructions', [], []);
        $provider = $this->provider();
        $prompt = new AgentPrompt($agent, 'Hello', [], $provider, 'gpt-test', invocationId: $sourceId);
        $options = TextGenerationOptions::forAgent($agent);

        event(new PromptingAgent($sourceId, $prompt));
        event(new StartingStep($sourceId, 0, $agent, $provider, 'gpt-test', true, [new UserMessage('Hello')], $options));
        event(new StepCompleted(
            $sourceId,
            0,
            $agent,
            $provider,
            'gpt-test',
            true,
            new StepResponse(
                text: '',
                toolCalls: [],
                finishReason: FinishReason::Stop,
                usage: new TextUsage(10, 5),
                meta: new Meta('openai', 'gpt-test'),
                structured: ['value' => 'OK'],
            ),
            10.0,
        ));
        event(new AgentPrompted($sourceId, $prompt, new StructuredAgentResponse(
            $sourceId,
            ['value' => 'OK'],
            '',
            new TextUsage(10, 5),
            new Meta('openai', 'gpt-test'),
        )));

        $pending = new PendingStep(
            number: 0,
            isFinalStep: true,
            provider: 'openai',
            model: 'gpt-test',
            instructions: 'Instructions',
            messages: [new UserMessage('Hello')],
            tools: [],
            schema: $agent->schema(new JsonSchemaTypeFactory),
            options: $options,
            invocationId: '0190b4a0-0000-7000-8000-000000000092',
        );

        $result = $this->app->make(ReplayRecordedStep::class)
            ->handle($pending, fn (): never => throw new \RuntimeException('Provider must not be called.'));

        $this->assertInstanceOf(StepResponse::class, $result);
        $this->assertSame(['value' => 'OK'], $result->structured);
    }
}
