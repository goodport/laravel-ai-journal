<?php

namespace Goodport\AiJournal\Journal\Replay;

final readonly class StepPayload
{
    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $fingerprintData
     */
    public function __construct(
        public array $request,
        public array $fingerprintData,
        public bool $stable = true,
    ) {}
}
