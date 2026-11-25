<?php

namespace Goodport\AiJournal\Journal\Replay;

use Goodport\AiJournal\Journal\Config\JournalConfig;
use Illuminate\Support\Facades\Context;

final class ContextReplayScope implements ReplayScope
{
    public function __construct(private JournalConfig $config) {}

    public function current(): ?string
    {
        $value = Context::get($this->config->scopeKey);

        return is_scalar($value) ? (string) $value : null;
    }
}
