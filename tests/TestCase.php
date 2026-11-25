<?php

namespace Goodport\AiJournal\Tests;

use Goodport\AiJournal\AiJournalManager;
use Goodport\AiJournal\AiJournalServiceProvider;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Journal\Recording\JournalFailureHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\StepFailed;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            AiJournalServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('ai-journal.enabled', true);
        $app['config']->set('ai-journal.replay.ttl', 0);
    }

    protected function provider(string $name = 'openai'): TextProvider
    {
        $provider = Mockery::mock(TextProvider::class);
        $provider->shouldReceive('name')->andReturn($name)->byDefault();

        return $provider;
    }

    protected function baseProvider(string $name = 'openai'): Provider
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('name')->andReturn($name)->byDefault();

        return $provider;
    }

    protected function prompt(string $invocationId, ?TextProvider $provider = null): AgentPrompt
    {
        return new AgentPrompt(
            agent: new AnonymousAgent('Instructions', [], []),
            prompt: 'Hello',
            attachments: [],
            provider: $provider ?? $this->provider(),
            model: 'gpt-test',
            invocationId: $invocationId,
        );
    }

    protected function startingStep(string $invocationId, ?AgentPrompt $prompt = null): StartingStep
    {
        $prompt ??= $this->prompt($invocationId);

        return new StartingStep(
            invocationId: $invocationId,
            stepNumber: 0,
            agent: $prompt->agent,
            provider: $prompt->provider,
            model: $prompt->model,
            isFinalStep: true,
            messages: [new UserMessage('Hello')],
            options: TextGenerationOptions::forAgent($prompt->agent),
        );
    }

    protected function stepResponse(string $text = 'OK', ?array $structured = null): StepResponse
    {
        return new StepResponse(
            text: $text,
            toolCalls: [],
            finishReason: FinishReason::Stop,
            usage: new TextUsage(10, 5),
            meta: new Meta('openai', 'gpt-test'),
            structured: $structured,
        );
    }

    protected function agentResponse(string $invocationId, string $text = 'OK'): AgentResponse
    {
        return new AgentResponse(
            invocationId: $invocationId,
            text: $text,
            usage: new TextUsage(10, 5),
            meta: new Meta('openai', 'gpt-test'),
        );
    }

    protected function dispatchPrompting(string $invocationId): AgentPrompt
    {
        $prompt = $this->prompt($invocationId);
        event(new PromptingAgent($invocationId, $prompt));

        return $prompt;
    }

    protected function dispatchCompletedStep(string $invocationId, AgentPrompt $prompt): void
    {
        event($this->startingStep($invocationId, $prompt));
        event(new StepCompleted(
            invocationId: $invocationId,
            stepNumber: 0,
            agent: $prompt->agent,
            provider: $prompt->provider,
            model: $prompt->model,
            isFinalStep: true,
            response: $this->stepResponse(),
            time: 12.5,
        ));
    }

    protected function dispatchPrompted(string $invocationId, AgentPrompt $prompt): void
    {
        event(new AgentPrompted($invocationId, $prompt, $this->agentResponse($invocationId)));
    }

    protected function dispatchFailedStep(string $invocationId, AgentPrompt $prompt): void
    {
        event($this->startingStep($invocationId, $prompt));
        event(new StepFailed(
            invocationId: $invocationId,
            stepNumber: 0,
            agent: $prompt->agent,
            provider: $prompt->provider,
            model: $prompt->model,
            isFinalStep: true,
            exception: new \RuntimeException('Provider failed'),
            time: 8.0,
        ));
    }

    protected function dispatchFailed(string $invocationId, AgentPrompt $prompt): void
    {
        event(new AgentFailed($invocationId, $prompt, new \RuntimeException('Provider failed')));
    }

    protected function pendingStep(string $invocationId, ?AnonymousAgent $agent = null, array $tools = []): PendingStep
    {
        $agent ??= new AnonymousAgent('Instructions', [], []);

        return new PendingStep(
            number: 0,
            isFinalStep: true,
            provider: 'openai',
            model: 'gpt-test',
            instructions: (string) $agent->instructions(),
            messages: [new UserMessage('Hello')],
            tools: $tools,
            schema: null,
            options: TextGenerationOptions::forAgent($agent),
            invocationId: $invocationId,
        );
    }

    protected function setJournalConfig(string $key, mixed $value): void
    {
        $this->app['config']->set('ai-journal.'.$key, $value);

        $this->app->forgetInstance(JournalConfig::class);
        $this->app->forgetInstance(JournalFailureHandler::class);
        $this->app->forgetInstance(AiJournalManager::class);
    }

    protected function dispatchFailover(string $invocationId, string $providerName = 'anthropic'): void
    {
        $provider = $this->baseProvider($providerName);

        event(new AgentFailedOver(
            invocationId: $invocationId,
            agent: new AnonymousAgent('Instructions', [], []),
            provider: $provider,
            model: 'gpt-test',
            exception: ProviderOverloadedException::forProvider($providerName),
        ));
    }
}
