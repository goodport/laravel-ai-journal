<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Facades\AiJournal;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiJournal\Tests\TestCase;

final class PruneTest extends TestCase
{
    public function test_old_invocations_and_steps_are_pruned(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000061';
        $prompt = $this->dispatchPrompting($invocationId);
        $this->dispatchCompletedStep($invocationId, $prompt);
        $this->dispatchPrompted($invocationId, $prompt);

        AiInvocation::query()->update(['created_at' => now()->subDays(2)]);
        AiInvocationStep::query()->update(['created_at' => now()->subDays(2)]);

        $this->assertSame(1, AiJournal::prune(1, true));
        $this->assertNotNull(AiInvocation::query()->where('invocation_id', $invocationId)->first());

        $this->assertSame(1, AiJournal::prune(1));
        $this->assertNull(AiInvocation::query()->where('invocation_id', $invocationId)->first());
        $this->assertSame(0, AiInvocationStep::query()->count());
    }

    public function test_prune_command_supports_dry_run(): void
    {
        $this->artisan('ai-journal:prune', ['--days' => '1', '--dry-run' => true])
            ->assertSuccessful();
    }
}
