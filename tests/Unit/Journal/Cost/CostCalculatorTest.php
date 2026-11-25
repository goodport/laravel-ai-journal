<?php

namespace Goodport\AiJournal\Tests\Unit\Journal\Cost;

use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Journal\Cost\CostCalculator;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiJournal\Tests\TestCase;

final class CostCalculatorTest extends TestCase
{
    public function test_cost_is_calculated_from_usage_and_rates(): void
    {
        $this->setJournalConfig('cost.providers', [
            'openai' => [
                'models' => [
                    'gpt-test' => [
                        'input' => 0.15,
                        'output' => 0.60,
                        'cache_read' => 0.075,
                        'cache_write' => 0.30,
                    ],
                ],
            ],
        ]);

        $calculator = $this->app->make(CostCalculator::class);

        $this->assertSame(0.75, $calculator->calculate([
            'input_tokens' => 1_000_000,
            'output_tokens' => 1_000_000,
        ], 'openai', 'gpt-test'));

        $this->assertSame(0.675, $calculator->calculate([
            'input_tokens' => 1_000_000,
            'output_tokens' => 1_000_000,
            'cache_read_input_tokens' => 1_000_000,
        ], 'openai', 'gpt-test'));

        $this->assertNull($calculator->calculate([
            'input_tokens' => 10,
            'output_tokens' => 5,
        ], 'unknown', 'unknown'));
    }

    public function test_sum_ignores_failed_steps_and_returns_null_for_unknown_cost(): void
    {
        $this->setJournalConfig('cost.providers', []);

        $calculator = $this->app->make(CostCalculator::class);

        $failed = new AiInvocationStep(['status' => StepStatus::Failed]);
        $succeeded = new AiInvocationStep(['status' => StepStatus::Succeeded, 'cost' => 0.25]);
        $replayed = new AiInvocationStep(['status' => StepStatus::Replayed, 'cost' => 0]);

        $this->assertSame(0.25, $calculator->sum([$failed, $succeeded, $replayed]));

        $unknown = new AiInvocationStep(['status' => StepStatus::Succeeded]);

        $this->assertNull($calculator->sum([$succeeded, $unknown]));
    }
}
