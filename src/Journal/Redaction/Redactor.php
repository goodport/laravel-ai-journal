<?php

namespace Goodport\AiJournal\Journal\Redaction;

interface Redactor
{
    public function redact(string $value): string;
}
