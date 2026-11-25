<?php

namespace Goodport\AiJournal\Journal\Pruning;

use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use InvalidArgumentException;

final class PruneJournal
{
    public function __construct(private JournalConfig $config) {}

    public function prune(?int $days = null, bool $dryRun = false): int
    {
        $days ??= $this->config->pruneDays;

        if ($days <= 0) {
            throw new InvalidArgumentException('Retention days must be greater than 0.');
        }

        $cutoff = now()->subDays($days);

        if ($dryRun) {
            return AiInvocation::query()
                ->where('created_at', '<', $cutoff)
                ->count();
        }

        $deleted = 0;

        AiInvocation::query()
            ->where('created_at', '<', $cutoff)
            ->select('id')
            ->chunkById(500, function ($invocations) use (&$deleted): void {
                $ids = $invocations->pluck('id')->all();

                if ($ids === []) {
                    return;
                }

                AiInvocationStep::query()->whereIn('ai_invocation_id', $ids)->delete();
                AiInvocation::query()->whereIn('id', $ids)->delete();

                $deleted += count($ids);
            });

        return $deleted;
    }
}
