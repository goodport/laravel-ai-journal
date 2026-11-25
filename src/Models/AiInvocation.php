<?php

namespace Goodport\AiJournal\Models;

use Goodport\AiJournal\Enums\InvocationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AiInvocation extends Model
{
    protected $table = 'ai_invocations';

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return config('ai-journal.connection') ?: parent::getConnectionName();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvocationStatus::class,
            'streamed' => 'boolean',
            'replayed' => 'boolean',
            'attachments' => 'array',
            'structured' => 'array',
            'usage' => 'array',
            'meta' => 'array',
            'cost' => 'decimal:6',
            'attempts' => 'array',
            'context' => 'array',
            'tags' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AiInvocationStep::class, 'ai_invocation_id')
            ->orderBy('attempt_number')
            ->orderBy('step_number');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_invocation_id', 'invocation_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_invocation_id', 'invocation_id');
    }

    public function scopeForTrace(Builder $query, string $traceId): void
    {
        $query->where('trace_id', $traceId);
    }

    public function scopeForScope(Builder $query, ?string $scope): void
    {
        $scope === null
            ? $query->whereNull('scope')
            : $query->where('scope', $scope);
    }

    public function scopeSucceeded(Builder $query): void
    {
        $query->where('status', InvocationStatus::Succeeded->value);
    }

    public function scopeFailed(Builder $query): void
    {
        $query->where('status', InvocationStatus::Failed->value);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', InvocationStatus::Pending->value);
    }
}
