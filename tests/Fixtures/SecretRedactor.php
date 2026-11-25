<?php

namespace Goodport\AiJournal\Tests\Fixtures;

use Goodport\AiJournal\Journal\Redaction\Redactor;

final class SecretRedactor implements Redactor
{
    public function redact(string $value): string
    {
        return str_replace('secret', '[redacted]', $value);
    }
}
