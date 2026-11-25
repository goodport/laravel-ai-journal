<?php

namespace Goodport\AiJournal\Journal\Replay;

use Closure;
use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Journal\Recording\InvocationRecorder;
use Goodport\AiJournal\Journal\Recording\InvocationStepRecorder;
use Goodport\AiJournal\Models\AiInvocationStep;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\PendingStep;
use Throwable;

final class ReplayRecordedStep
{
    public function __construct(
        private JournalConfig $config,
        private StepPayloadFactory $payloads,
        private StepFingerprint $fingerprints,
        private StepResponseMapper $mapper,
        private ReplayScope $scope,
        private InvocationRecorder $invocations,
        private InvocationStepRecorder $steps,
    ) {}

    public function handle(PendingStep $step, Closure $next): mixed
    {
        if (! $this->canReplay($step)) {
            return $next($step);
        }

        try {
            $payload = $this->payloads->fromPendingStep($step);
            $fingerprint = $this->fingerprints->fromPayload($payload);

            if ($fingerprint === null) {
                return $next($step);
            }

            $source = $this->findSource($fingerprint, $this->scope->current());

            if ($source === null || ! is_array($source->response) || ! $this->mapper->isReplaySafe($source->response)) {
                return $next($step);
            }

            $response = $this->mapper->fromArray($source->response);
            $startedAt = hrtime(true);
            $invocation = $this->invocations->ensureForPendingStep($step);
            $attempt = $this->invocations->attemptFor($invocation);

            $this->steps->replay(
                $invocation,
                $step,
                $source,
                $response,
                $payload,
                $attempt,
                $fingerprint,
                $this->elapsedMilliseconds($startedAt),
            );

            return $response;
        } catch (Throwable $exception) {
            report($exception);

            if ($this->config->throw) {
                throw $exception;
            }

            return $next($step);
        }
    }

    private function canReplay(PendingStep $step): bool
    {
        if (! $this->config->enabled || ! $this->config->replayEnabled) {
            return false;
        }

        if ($this->config->replayTtl <= 0 || ! $this->config->captureResponse || $this->config->redactor !== null) {
            return false;
        }

        if ($this->config->firstStepOnly && $step->number !== 0) {
            return false;
        }

        if (! $this->config->allowTools && $step->tools !== []) {
            return false;
        }

        return true;
    }

    private function findSource(string $fingerprint, ?string $scope): ?AiInvocationStep
    {
        return AiInvocationStep::query()
            ->where('request_fingerprint', $fingerprint)
            ->where('status', StepStatus::Succeeded->value)
            ->where('created_at', '>=', now()->subSeconds($this->config->replayTtl))
            ->whereHas('invocation', function (Builder $query) use ($scope): void {
                $scope === null
                    ? $query->whereNull('scope')
                    : $query->where('scope', $scope);
            })
            ->latest('created_at')
            ->first();
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
