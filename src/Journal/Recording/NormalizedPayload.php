<?php

namespace Goodport\AiJournal\Journal\Recording;

final readonly class NormalizedPayload
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(
        public array $data,
        public bool $stable = true,
    ) {}
}
