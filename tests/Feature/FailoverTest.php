<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Enums\AttemptStatus;
use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiJournal\Tests\TestCase;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Prompts\AgentPrompt;

final class FailoverTest extends TestCase
{
    public function test_failover_keeps_one_invocation_and_separate_attempts(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000021';
        $firstPrompt = $this->dispatchPrompting($invocationId);

        $this->dispatchFailedStep($invocationId, $firstPrompt);
        $this->dispatchFailover($invocationId, 'anthropic');

        $secondPrompt = new AgentPrompt(
            agent: new AnonymousAgent('Instructions', [], []),
            prompt: 'Hello',
            attachments: [],
            provider: $this->provider('anthropic'),
            model: 'gpt-test',
            invocationId: $invocationId,
        );

        event(new PromptingAgent($invocationId, $secondPrompt));
        $this->dispatchCompletedStep($invocationId, $secondPrompt);
        $this->dispatchPrompted($invocationId, $secondPrompt);

        $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();

        $this->assertSame(2, $invocation->attempt_count);
        $this->assertSame(AttemptStatus::Succeeded->value, $invocation->attempts[1]['status']);
        $this->assertSame(AttemptStatus::Failed->value, $invocation->attempts[0]['status']);

        foreach (['error_code', 'http_status', 'provider_request_id', 'retry_after', 'error_details'] as $key) {
            $this->assertArrayHasKey($key, $invocation->attempts[0]);
            $this->assertArrayHasKey($key, $invocation->attempts[1]);
        }

        $steps = AiInvocationStep::query()
            ->where('ai_invocation_id', $invocation->id)
            ->orderBy('attempt_number')
            ->get();

        $this->assertCount(2, $steps);
        $this->assertSame(1, $steps[0]->attempt_number);
        $this->assertSame(StepStatus::Failed, $steps[0]->status);
        $this->assertSame(2, $steps[1]->attempt_number);
        $this->assertSame(StepStatus::Succeeded, $steps[1]->status);
    }
}
