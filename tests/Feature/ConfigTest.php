<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Journal\Config\InvalidConfigurationException;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Tests\TestCase;

final class ConfigTest extends TestCase
{
    public function test_invalid_attachment_capture_mode_is_rejected(): void
    {
        $this->setJournalConfig('capture.attachments', 'files');

        $this->expectException(InvalidConfigurationException::class);

        $this->app->make(JournalConfig::class);
    }

    public function test_invalid_replay_scope_is_rejected(): void
    {
        $this->setJournalConfig('replay.scope', \stdClass::class);

        $this->expectException(InvalidConfigurationException::class);

        $this->app->make(JournalConfig::class);
    }

    public function test_hash_remote_mode_requires_a_hasher(): void
    {
        $this->setJournalConfig('attachments.remote.mode', 'hash');
        $this->setJournalConfig('attachments.remote.hasher', null);

        $this->expectException(InvalidConfigurationException::class);

        $this->app->make(JournalConfig::class);
    }
}
