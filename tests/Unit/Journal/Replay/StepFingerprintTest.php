<?php

namespace Goodport\AiJournal\Tests\Unit\Journal\Replay;

use Goodport\AiJournal\Journal\Recording\PayloadNormalizer;
use Goodport\AiJournal\Journal\Recording\UploadedFileNormalizer;
use Goodport\AiJournal\Journal\Replay\StepFingerprint;
use Goodport\AiJournal\Journal\Replay\StepPayload;
use PHPUnit\Framework\TestCase;

final class StepFingerprintTest extends TestCase
{
    public function test_fingerprint_is_stable_and_depends_on_payload(): void
    {
        $fingerprint = new StepFingerprint(new PayloadNormalizer(new UploadedFileNormalizer));

        $first = new StepPayload(['model' => 'gpt-test', 'messages' => ['Hello']], ['b' => 2, 'a' => 1]);
        $second = new StepPayload(['model' => 'gpt-test', 'messages' => ['Hello']], ['a' => 1, 'b' => 2]);
        $different = new StepPayload(['model' => 'other', 'messages' => ['Hello']], ['a' => 1, 'b' => 3]);

        $this->assertSame($fingerprint->fromPayload($first), $fingerprint->fromPayload($second));
        $this->assertNotSame($fingerprint->fromPayload($first), $fingerprint->fromPayload($different));
    }

    public function test_unstable_payload_has_no_fingerprint(): void
    {
        $fingerprint = new StepFingerprint(new PayloadNormalizer(new UploadedFileNormalizer));

        $this->assertNull($fingerprint->fromPayload(new StepPayload([], [], false)));
    }
}
