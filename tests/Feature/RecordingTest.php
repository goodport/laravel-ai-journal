<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Enums\InvocationStatus;
use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiJournal\Tests\TestCase;

final class RecordingTest extends TestCase
{
    public function test_successful_invocation_and_step_are_recorded(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000001';
        $prompt = $this->dispatchPrompting($invocationId);

        $this->dispatchCompletedStep($invocationId, $prompt);
        $this->dispatchPrompted($invocationId, $prompt);

        $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();

        $this->assertSame(InvocationStatus::Succeeded, $invocation->status);
        $this->assertSame('OK', $invocation->response_text);
        $this->assertSame(1, $invocation->attempt_count);
        $this->assertSame(10, $invocation->usage['input_tokens']);

        $step = AiInvocationStep::query()->where('ai_invocation_id', $invocation->id)->firstOrFail();

        $this->assertSame(StepStatus::Succeeded, $step->status);
        $this->assertSame(0, $step->step_number);
        $this->assertSame(1, $step->attempt_number);
        $this->assertSame('OK', $step->response['text']);
        $this->assertSame(13, $step->duration_ms);
    }
}
