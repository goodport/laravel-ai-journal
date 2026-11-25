<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Facades\AiJournal;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Tests\TestCase;
use Illuminate\Support\Facades\Context;

final class ContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Context::flush();
        Context::add([
            'ai.trace_id' => 'trace-123',
            'ai.check' => 'post_body',
            'ai.pass' => 1,
            'ai.journal.scope' => 'tenant-a',
            'ai.journal.tags' => ['source' => 'test'],
        ]);
    }

    protected function tearDown(): void
    {
        Context::flush();

        parent::tearDown();
    }

    public function test_context_is_stored_and_trace_can_be_queried(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000121';
        $this->dispatchPrompting($invocationId);

        $invocation = AiJournal::trace('trace-123')->first();

        $this->assertInstanceOf(AiInvocation::class, $invocation);
        $this->assertSame('trace-123', $invocation->trace_id);
        $this->assertSame('tenant-a', $invocation->scope);
        $this->assertSame('post_body', $invocation->context['ai.check']);
        $this->assertSame(['source' => 'test'], $invocation->tags);
        $this->assertSame($invocation->id, AiJournal::invocation($invocationId)?->id);
    }
}
