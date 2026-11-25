<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Enums\InvocationStatus;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Tests\TestCase;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\StreamingAgent;

final class StreamingTest extends TestCase
{
    public function test_streaming_invocation_is_marked_as_streamed(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000041';
        $prompt = $this->prompt($invocationId);

        event(new StreamingAgent($invocationId, $prompt));
        event($this->startingStep($invocationId, $prompt));
        event(new StepCompleted(
            invocationId: $invocationId,
            stepNumber: 0,
            agent: $prompt->agent,
            provider: $prompt->provider,
            model: $prompt->model,
            isFinalStep: true,
            response: $this->stepResponse(),
            time: 10.0,
        ));
        event(new AgentStreamed($invocationId, $prompt, $this->agentResponse($invocationId)));

        $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();

        $this->assertTrue($invocation->streamed);
        $this->assertSame(1, $invocation->attempt_count);
        $this->assertSame(InvocationStatus::Succeeded, $invocation->status);
    }
}
