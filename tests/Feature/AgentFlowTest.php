<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiJournal\Tests\Fixtures\ReplayAgent;
use Goodport\AiJournal\Tests\TestCase;

final class AgentFlowTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.replay.ttl', 3600);
    }

    public function test_real_agent_flow_is_journaled_and_replayed(): void
    {
        ReplayAgent::fake(['OK'])->preventStrayPrompts();

        $agent = new ReplayAgent('Instructions', [], []);

        $first = $agent->prompt('Hello', provider: 'openai', model: 'gpt-test');
        $second = $agent->prompt('Hello', provider: 'openai', model: 'gpt-test');

        $this->assertSame('OK', $first->text);
        $this->assertSame('OK', $second->text);
        $this->assertSame(2, AiInvocation::query()->count());
        $this->assertTrue(AiInvocation::query()->where('replayed', true)->exists());

        $replayStep = AiInvocationStep::query()
            ->where('status', StepStatus::Replayed->value)
            ->firstOrFail();

        $this->assertNotNull($replayStep->replay_of_step_id);
        $this->assertEquals(0, $replayStep->cost);
    }
}
