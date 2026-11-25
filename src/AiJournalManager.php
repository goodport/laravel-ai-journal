<?php

namespace Goodport\AiJournal;

use Goodport\AiJournal\Journal\Pruning\PruneJournal;
use Goodport\AiJournal\Models\AiInvocation;
use Illuminate\Support\Collection;

final class AiJournalManager
{
    public function __construct(private PruneJournal $pruneJournal) {}

    public function invocation(string $invocationId): ?AiInvocation
    {
        return AiInvocation::query()
            ->where('invocation_id', $invocationId)
            ->first();
    }

    /**
     * @return Collection<int, AiInvocation>
     */
    public function trace(string $traceId): Collection
    {
        return AiInvocation::query()
            ->forTrace($traceId)
            ->latest('created_at')
            ->get();
    }

    public function prune(?int $days = null, bool $dryRun = false): int
    {
        return $this->pruneJournal->prune($days, $dryRun);
    }
}
