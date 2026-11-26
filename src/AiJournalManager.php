<?php

namespace Goodport\AiJournal;

use Goodport\AiJournal\Journal\Pruning\PruneJournal;
use Goodport\AiJournal\Models\AiInvocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class AiJournalManager
{
    /**
     * @var array<int, string>
     */
    private const LIST_COLUMNS = [
        'id',
        'invocation_id',
        'parent_invocation_id',
        'parent_tool_invocation_id',
        'trace_id',
        'agent',
        'provider',
        'model',
        'status',
        'streamed',
        'replayed',
        'scope',
        'cost',
        'currency',
        'error_class',
        'error_message',
        'error_code',
        'http_status',
        'provider_request_id',
        'retry_after',
        'attempt_count',
        'conversation_id',
        'duration_ms',
        'started_at',
        'finished_at',
        'created_at',
        'updated_at',
    ];

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

    /**
     * @param  array{
     *     agent?: string,
     *     provider?: string,
     *     model?: string,
     *     status?: string,
     *     scope?: string|null,
     *     trace_id?: string,
     *     error_code?: string,
     *     from?: \DateTimeInterface|string,
     *     to?: \DateTimeInterface|string
     * }  $filters
     */
    public function invocations(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = AiInvocation::query()
            ->select(self::LIST_COLUMNS)
            ->latest('created_at');

        if (isset($filters['agent'])) {
            $query->where('agent', $filters['agent']);
        }

        if (isset($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (isset($filters['model'])) {
            $query->where('model', $filters['model']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('scope', $filters)) {
            $query->forScope($filters['scope']);
        }

        if (isset($filters['trace_id'])) {
            $query->forTrace($filters['trace_id']);
        }

        if (isset($filters['error_code'])) {
            $query->where('error_code', $filters['error_code']);
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }

    public function prune(?int $days = null, bool $dryRun = false): int
    {
        return $this->pruneJournal->prune($days, $dryRun);
    }
}
