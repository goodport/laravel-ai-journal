<?php

namespace Goodport\AiJournal\Models;

use Goodport\AiJournal\Enums\StepStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AiInvocationStep extends Model
{
    protected $table = 'ai_invocation_steps';

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
            'status' => StepStatus::class,
            'request' => 'array',
            'response' => 'array',
            'usage' => 'array',
            'cost' => 'decimal:6',
            'http_status' => 'integer',
            'retry_after' => 'integer',
            'error_details' => 'array',
        ];
    }

    public function invocation(): BelongsTo
    {
        return $this->belongsTo(AiInvocation::class, 'ai_invocation_id');
    }

    public function replayOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replay_of_step_id');
    }

    public function replays(): HasMany
    {
        return $this->hasMany(self::class, 'replay_of_step_id');
    }

    public function scopeSucceeded(Builder $query): void
    {
        $query->where('status', StepStatus::Succeeded->value);
    }

    public function scopeFailed(Builder $query): void
    {
        $query->where('status', StepStatus::Failed->value);
    }

    public function scopeReplayed(Builder $query): void
    {
        $query->where('status', StepStatus::Replayed->value);
    }

    public function scopeForFingerprint(Builder $query, string $fingerprint): void
    {
        $query->where('request_fingerprint', $fingerprint);
    }
}
