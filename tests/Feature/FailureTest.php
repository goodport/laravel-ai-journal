<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Enums\AttemptStatus;
use Goodport\AiJournal\Enums\InvocationStatus;
use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiJournal\Tests\TestCase;

final class FailureTest extends TestCase
{
    public function test_failed_invocation_and_step_are_recorded(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000031';
        $prompt = $this->dispatchPrompting($invocationId);

        $this->dispatchFailedStep($invocationId, $prompt);
        $this->dispatchFailed($invocationId, $prompt);

        $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();
        $step = AiInvocationStep::query()->where('ai_invocation_id', $invocation->id)->firstOrFail();

        $this->assertSame(InvocationStatus::Failed, $invocation->status);
        $this->assertSame(\RuntimeException::class, $invocation->error_class);
        $this->assertSame(AttemptStatus::Failed->value, $invocation->attempts[0]['status']);
        $this->assertSame(StepStatus::Failed, $step->status);
        $this->assertSame(\RuntimeException::class, $step->error_class);
    }
}
