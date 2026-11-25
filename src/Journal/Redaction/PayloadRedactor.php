<?php

namespace Goodport\AiJournal\Journal\Redaction;

final class PayloadRedactor
{
    public function __construct(private Redactor $redactor) {}

    /**
     * Recursively redact strings in the stored payload.
     */
    public function redact(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->redactString($value);
        }

        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $key => $item) {
                $redacted[$key] = $this->redact($item);
            }

            return $redacted;
        }

        return $value;
    }

    public function redactString(string $value): string
    {
        return $this->redactor->redact($value);
    }
}
