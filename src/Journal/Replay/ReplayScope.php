<?php

namespace Goodport\AiJournal\Journal\Replay;

interface ReplayScope
{
    public function current(): ?string;
}
