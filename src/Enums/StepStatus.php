<?php

namespace Goodport\AiJournal\Enums;

enum StepStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Replayed = 'replayed';
}
