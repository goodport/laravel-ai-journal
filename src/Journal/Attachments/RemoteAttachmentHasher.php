<?php

namespace Goodport\AiJournal\Journal\Attachments;

interface RemoteAttachmentHasher
{
    /**
     * Return a stable content hash for a remote attachment, or null when it cannot be read.
     */
    public function hash(string $url): ?string;
}
