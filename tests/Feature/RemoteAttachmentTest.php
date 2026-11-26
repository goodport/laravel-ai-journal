<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Tests\Fixtures\FakeRemoteAttachmentHasher;
use Goodport\AiJournal\Tests\TestCase;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Files\RemoteImage;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;

final class RemoteAttachmentTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.replay.ttl', 3600);
        $app['config']->set('ai-journal.attachments.remote.mode', 'hash');
        $app['config']->set('ai-journal.attachments.remote.hasher', FakeRemoteAttachmentHasher::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        FakeRemoteAttachmentHasher::$hashes = [];
    }

    /**
     * A remote file with the same content hash is replayed.
     */
    public function test_same_url_with_same_hash_is_replayed(): void
    {
        $url = 'https://example.com/avatar.png';
        $sourceInvocationId = '0190b4a0-0000-7000-8000-000000000043';
        $sourcePrompt = $this->dispatchRemoteStep($sourceInvocationId, $url, 'hash-v1');

        $this->completeRemoteStep($sourceInvocationId, $sourcePrompt);
        $this->dispatchPrompted($sourceInvocationId, $sourcePrompt);

        $pending = $this->pendingRemoteStep('0190b4a0-0000-7000-8000-000000000044', $url);
        $middleware = $this->app->make(ReplayRecordedStep::class);
        $nextCalls = 0;

        $result = $middleware->handle($pending, function () use (&$nextCalls): mixed {
            $nextCalls++;

            return $this->stepResponse('Fresh response');
        });

        $this->assertSame(0, $nextCalls);
        $this->assertSame('OK', $result->text);
    }

    /**
     * A changed remote file at the same URL cannot reuse the old response.
     */
    public function test_same_url_with_changed_content_is_not_replayed(): void
    {
        $url = 'https://example.com/avatar.png';
        $sourceInvocationId = '0190b4a0-0000-7000-8000-000000000041';
        $sourcePrompt = $this->dispatchRemoteStep($sourceInvocationId, $url, 'hash-v1');

        $this->completeRemoteStep($sourceInvocationId, $sourcePrompt);
        $this->dispatchPrompted($sourceInvocationId, $sourcePrompt);

        FakeRemoteAttachmentHasher::$hashes[$url] = 'hash-v2';

        $pending = $this->pendingRemoteStep('0190b4a0-0000-7000-8000-000000000042', $url);

        $nextCalled = false;
        $middleware = $this->app->make(ReplayRecordedStep::class);

        $result = $middleware->handle($pending, function () use (&$nextCalled): mixed {
            $nextCalled = true;

            return $this->stepResponse('Fresh response');
        });

        $this->assertTrue($nextCalled);
        $this->assertSame('Fresh response', $result->text);
    }

    private function pendingRemoteStep(string $invocationId, string $url): PendingStep
    {
        $agent = new AnonymousAgent('Instructions', [], []);

        return new PendingStep(
            number: 0,
            isFinalStep: true,
            provider: 'openai',
            model: 'gpt-test',
            instructions: 'Instructions',
            messages: [new UserMessage('Hello', [new RemoteImage($url)])],
            tools: [],
            schema: null,
            options: TextGenerationOptions::forAgent($agent),
            invocationId: $invocationId,
        );
    }

    private function completeRemoteStep(string $invocationId, AgentPrompt $prompt): void
    {
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

    private function dispatchRemoteStep(string $invocationId, string $url, string $hash): AgentPrompt
    {
        FakeRemoteAttachmentHasher::$hashes[$url] = $hash;

        $agent = new AnonymousAgent('Instructions', [], []);
        $prompt = new AgentPrompt(
            agent: $agent,
            prompt: 'Hello',
            attachments: [],
            provider: $this->provider(),
            model: 'gpt-test',
            invocationId: $invocationId,
        );

        event(new PromptingAgent($invocationId, $prompt));
        event(new StartingStep(
            invocationId: $invocationId,
            stepNumber: 0,
            agent: $agent,
            provider: $prompt->provider,
            model: $prompt->model,
            isFinalStep: true,
            messages: [new UserMessage('Hello', [new RemoteImage($url)])],
            options: TextGenerationOptions::forAgent($agent),
        ));

        return $prompt;
    }
}
