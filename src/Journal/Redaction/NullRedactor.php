<?php

namespace Goodport\AiJournal\Journal\Redaction;

final class NullRedactor implements Redactor
{
    public function redact(string $value): string
    {
        return $value;
    }
}
