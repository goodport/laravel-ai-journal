<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Enums\InvocationStatus;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Tests\TestCase;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Responses\AgentResponse;

final class ApprovalTest extends TestCase
{
    public function test_pending_approval_changes_invocation_status(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000051';
        $prompt = $this->dispatchPrompting($invocationId);
        $response = AgentResponse::fakeWithPendingApprovals([
            new PendingApproval('approval-id', 'example_tool', []),
        ]);

        event(new AgentPrompted($invocationId, $prompt, $response));

        $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();

        $this->assertSame(InvocationStatus::PendingApproval, $invocation->status);
    }
}
