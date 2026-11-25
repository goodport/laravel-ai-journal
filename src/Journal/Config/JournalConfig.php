<?php

namespace Goodport\AiJournal\Journal\Config;

final readonly class JournalConfig
{
    /**
     * @param  array<int, string>  $fingerprintContext
     * @param  array<string, mixed>  $costProviders
     */
    public function __construct(
        public bool $enabled,
        public ?string $connection,
        public bool $capturePrompt,
        public bool $captureResponse,
        public bool $captureStepRequest,
        public string $captureAttachments,
        public bool $captureContext,
        public ?string $redactor,
        public string $traceKey,
        public string $scopeKey,
        public string $tagsKey,
        public bool $replayEnabled,
        public int $replayTtl,
        public string $replayScope,
        public array $fingerprintContext,
        public bool $allowTools,
        public bool $firstStepOnly,
        public string $currency,
        public array $costProviders,
        public int $pruneDays,
        public bool $throw,
    ) {}
}
