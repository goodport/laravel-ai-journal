<?php

namespace Goodport\AiJournal\Journal\Recording;

final class AttemptRegistry
{
    /** @var array<string, int> */
    private array $attempts = [];

    public function set(string $invocationId, int $attempt): void
    {
        $this->attempts[$invocationId] = $attempt;
    }

    public function current(string $invocationId): ?int
    {
        return $this->attempts[$invocationId] ?? null;
    }

    public function forget(string $invocationId): void
    {
        unset($this->attempts[$invocationId]);
    }
}
