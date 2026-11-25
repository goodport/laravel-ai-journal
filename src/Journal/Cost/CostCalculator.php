<?php

namespace Goodport\AiJournal\Journal\Cost;

use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Models\AiInvocationStep;

final class CostCalculator
{
    public function __construct(private JournalConfig $config) {}

    /**
     * @param  array<string, mixed>  $usage
     */
    public function calculate(array $usage, string $provider, string $model): ?float
    {
        $rates = $this->config->costProviders[$provider]['models'][$model] ?? null;

        if (! is_array($rates) || ! isset($rates['input'], $rates['output'])) {
            return null;
        }

        $input = (int) ($usage['input_tokens'] ?? 0);
        $cacheRead = (int) ($usage['cache_read_input_tokens'] ?? 0);
        $cacheWrite = (int) ($usage['cache_write_input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);

        $uncachedInput = max(0, $input - $cacheRead - $cacheWrite);

        $cost = (
            $uncachedInput * (float) $rates['input']
            + $cacheRead * (float) ($rates['cache_read'] ?? $rates['input'])
            + $cacheWrite * (float) ($rates['cache_write'] ?? $rates['input'])
            + $output * (float) $rates['output']
        ) / 1_000_000;

        return round($cost, 6);
    }

    /**
     * @param  iterable<int, AiInvocationStep>  $steps
     */
    public function sum(iterable $steps): ?float
    {
        $total = 0.0;
        $hasCost = false;

        foreach ($steps as $step) {
            if ($step->status === StepStatus::Failed || $step->status === StepStatus::Pending) {
                continue;
            }

            if ($step->cost === null) {
                return null;
            }

            $total += (float) $step->cost;
            $hasCost = true;
        }

        return $hasCost ? round($total, 6) : null;
    }

    public function currency(): string
    {
        return $this->config->currency;
    }
}
