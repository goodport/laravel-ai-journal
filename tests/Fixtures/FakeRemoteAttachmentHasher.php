<?php

namespace Goodport\AiJournal\Tests\Fixtures;

use Goodport\AiJournal\Journal\Attachments\RemoteAttachmentHasher;

final class FakeRemoteAttachmentHasher implements RemoteAttachmentHasher
{
    /** @var array<string, string|null> */
    public static array $hashes = [];

    public function hash(string $url): ?string
    {
        return self::$hashes[$url] ?? null;
    }
}
