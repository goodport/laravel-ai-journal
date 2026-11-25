<?php

namespace Goodport\AiJournal\Enums;

enum InvocationStatus: string
{
    case Pending = 'pending';
    case PendingApproval = 'pending_approval';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
