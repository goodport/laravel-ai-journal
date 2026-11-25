<?php

namespace Goodport\AiJournal\Journal\Context;

use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Journal\Recording\PayloadNormalizer;
use Goodport\AiJournal\Journal\Redaction\PayloadRedactor;
use Illuminate\Support\Facades\Context;

final class ContextReader
{
    public function __construct(
        private JournalConfig $config,
        private PayloadNormalizer $normalizer,
        private PayloadRedactor $redactor,
    ) {}

    public function traceId(): ?string
    {
        $value = Context::get($this->config->traceKey);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function context(): ?array
    {
        if (! $this->config->captureContext) {
            return null;
        }

        return $this->redactor->redact($this->normalizer->normalize(Context::all()));
    }

    /**
     * @return array<int, mixed>|null
     */
    public function tags(): ?array
    {
        if (! $this->config->captureContext) {
            return null;
        }

        $value = Context::get($this->config->tagsKey);

        if ($value === null) {
            return null;
        }

        $tags = is_array($value) ? $value : [$value];

        return $this->redactor->redact($this->normalizer->normalizeArray($tags));
    }

    /**
     * Context included in the fingerprint. It is not redacted.
     *
     * @return array<string, mixed>
     */
    public function fingerprintContext(): array
    {
        if ($this->config->fingerprintContext === []) {
            return [];
        }

        return $this->normalizer->normalizeArray(Context::only($this->config->fingerprintContext));
    }
}
