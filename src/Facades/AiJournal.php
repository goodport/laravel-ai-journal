<?php

namespace Goodport\AiJournal\Facades;

use Goodport\AiJournal\AiJournalManager;
use Goodport\AiJournal\Models\AiInvocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static AiInvocation|null invocation(string $invocationId)
 * @method static Collection<int, AiInvocation> trace(string $traceId)
 * @method static LengthAwarePaginator invocations(array<string, mixed> $filters = [], int $perPage = 50)
 * @method static int prune(int|null $days = null, bool $dryRun = false)
 *
 * @see AiJournalManager
 */
final class AiJournal extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiJournalManager::class;
    }
}
