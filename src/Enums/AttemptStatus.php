<?php

namespace Goodport\AiJournal\Enums;

enum AttemptStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
