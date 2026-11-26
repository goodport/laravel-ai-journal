<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiJournal\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;

final class UploadedFileReplayTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.replay.ttl', 3600);
    }

    /**
     * Uploaded files with the same content are replay-compatible.
     */
    public function test_uploaded_file_with_same_content_is_replayed(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000071';
        $sourcePrompt = $this->dispatchUploadedStep($invocationId, 'same-content');
        $this->completeUploadedStep($invocationId, $sourcePrompt);
        $this->dispatchPrompted($invocationId, $sourcePrompt);

        $pending = $this->pendingUploadedStep(
            '0190b4a0-0000-7000-8000-000000000072',
            'same-content',
        );
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
     * Changed upload content cannot reuse the previous response.
     */
    public function test_uploaded_file_with_changed_content_is_not_replayed(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000073';
        $sourcePrompt = $this->dispatchUploadedStep($invocationId, 'old-content');
        $this->completeUploadedStep($invocationId, $sourcePrompt);
        $this->dispatchPrompted($invocationId, $sourcePrompt);

        $pending = $this->pendingUploadedStep(
            '0190b4a0-0000-7000-8000-000000000074',
            'new-content',
        );
        $middleware = $this->app->make(ReplayRecordedStep::class);
        $nextCalls = 0;

        $result = $middleware->handle($pending, function () use (&$nextCalls): mixed {
            $nextCalls++;

            return $this->stepResponse('Fresh response');
        });

        $this->assertSame(1, $nextCalls);
        $this->assertSame('Fresh response', $result->text);
    }

    private function completeUploadedStep(string $invocationId, AgentPrompt $prompt): void
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

    private function dispatchUploadedStep(string $invocationId, string $content): AgentPrompt
    {
        $agent = new AnonymousAgent('Instructions', [], []);
        $file = UploadedFile::fake()->createWithContent('avatar.png', $content);
        $prompt = new AgentPrompt(
            agent: $agent,
            prompt: 'Hello',
            attachments: [$file],
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
            messages: [new UserMessage('Hello', [$file])],
            options: TextGenerationOptions::forAgent($agent),
        ));

        return $prompt;
    }

    private function pendingUploadedStep(string $invocationId, string $content): PendingStep
    {
        $agent = new AnonymousAgent('Instructions', [], []);
        $file = UploadedFile::fake()->createWithContent('avatar.png', $content);

        return new PendingStep(
            number: 0,
            isFinalStep: true,
            provider: 'openai',
            model: 'gpt-test',
            instructions: 'Instructions',
            messages: [new UserMessage('Hello', [$file])],
            tools: [],
            schema: null,
            options: TextGenerationOptions::forAgent($agent),
            invocationId: $invocationId,
        );
    }
}
