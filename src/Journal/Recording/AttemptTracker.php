<?php

namespace Goodport\AiJournal\Journal\Recording;

use DateTimeInterface;
use Goodport\AiJournal\Enums\AttemptStatus;
use Goodport\AiJournal\Models\AiInvocation;

final class AttemptTracker
{
    public function __construct(private AttemptRegistry $registry) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function initial(string $provider, string $model, DateTimeInterface $at): array
    {
        return [[
            'number' => 1,
            'provider' => $provider,
            'model' => $model,
            'status' => AttemptStatus::Pending->value,
            'started_at' => $at->format(DATE_ATOM),
        ]];
    }

    public function start(AiInvocation $invocation, string $provider, string $model, DateTimeInterface $at): int
    {
        $attempt = $invocation->exists
            ? max(1, (int) $invocation->attempt_count) + 1
            : 1;

        $invocation->attempt_count = $attempt;
        $invocation->attempts = $this->append($invocation->attempts ?? [], [
            'number' => $attempt,
            'provider' => $provider,
            'model' => $model,
            'status' => AttemptStatus::Pending->value,
            'started_at' => $at->format(DATE_ATOM),
        ]);

        $this->registry->set((string) $invocation->invocation_id, $attempt);

        return $attempt;
    }

    public function current(AiInvocation $invocation): int
    {
        $attempt = $this->registry->current((string) $invocation->invocation_id)
            ?? max(1, (int) $invocation->attempt_count);

        $this->registry->set((string) $invocation->invocation_id, $attempt);

        return $attempt;
    }

    public function succeed(
        AiInvocation $invocation,
        int $attempt,
        ?string $provider,
        ?string $model,
        DateTimeInterface $at,
    ): void {
        $invocation->attempts = $this->mark($invocation->attempts ?? [], $attempt, [
            'status' => AttemptStatus::Succeeded->value,
            'provider' => $provider,
            'model' => $model,
            'finished_at' => $at->format(DATE_ATOM),
        ]);
        $invocation->attempt_count = max((int) $invocation->attempt_count, $attempt);
    }

    public function fail(
        AiInvocation $invocation,
        int $attempt,
        string $errorClass,
        string $errorMessage,
        DateTimeInterface $at,
    ): void {
        $invocation->attempts = $this->mark($invocation->attempts ?? [], $attempt, [
            'status' => AttemptStatus::Failed->value,
            'error_class' => $errorClass,
            'error_message' => $errorMessage,
            'finished_at' => $at->format(DATE_ATOM),
        ]);
        $invocation->attempt_count = max((int) $invocation->attempt_count, $attempt);
    }

    public function failover(
        AiInvocation $invocation,
        int $attempt,
        string $provider,
        string $model,
        string $errorClass,
        string $errorMessage,
        DateTimeInterface $at,
    ): void {
        $invocation->attempts = $this->mark($invocation->attempts ?? [], $attempt, [
            'status' => AttemptStatus::Failed->value,
            'provider' => $provider,
            'model' => $model,
            'error_class' => $errorClass,
            'error_message' => $errorMessage,
            'finished_at' => $at->format(DATE_ATOM),
        ]);
        $invocation->attempt_count = max((int) $invocation->attempt_count, $attempt);
    }

    public function forget(AiInvocation $invocation): void
    {
        $this->registry->forget((string) $invocation->invocation_id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $attempts
     * @param  array<string, mixed>  $values
     * @return array<int, array<string, mixed>>
     */
    private function mark(array $attempts, int $number, array $values): array
    {
        return $this->append($attempts, ['number' => $number, ...$values]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $attempts
     * @param  array<string, mixed>  $values
     * @return array<int, array<string, mixed>>
     */
    private function append(array $attempts, array $values): array
    {
        foreach ($attempts as $index => $attempt) {
            if (($attempt['number'] ?? null) === ($values['number'] ?? null)) {
                $attempts[$index] = [...$attempt, ...$values];

                return $attempts;
            }
        }

        $attempts[] = $values;

        return $attempts;
    }
}
