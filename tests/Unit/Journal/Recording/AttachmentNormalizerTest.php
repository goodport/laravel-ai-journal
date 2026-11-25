<?php

namespace Goodport\AiJournal\Tests\Unit\Journal\Recording;

use Goodport\AiJournal\Journal\Recording\AttachmentNormalizer;
use Goodport\AiJournal\Journal\Recording\PayloadNormalizer;
use Laravel\Ai\Files\Base64Image;
use PHPUnit\Framework\TestCase;

final class AttachmentNormalizerTest extends TestCase
{
    public function test_base64_attachment_is_stored_as_metadata(): void
    {
        $normalizer = new AttachmentNormalizer(new PayloadNormalizer);
        $result = $normalizer->normalize(new Base64Image(base64_encode('image-content'), 'image/png'));

        $this->assertTrue($result->stable);
        $this->assertArrayNotHasKey('base64', $result->data);
        $this->assertSame(hash('sha256', 'image-content'), $result->data['base64_hash']);
        $this->assertSame(strlen('image-content'), $result->data['size']);
    }

    public function test_attachment_without_stable_reference_is_not_stable(): void
    {
        $normalizer = new AttachmentNormalizer(new PayloadNormalizer);
        $result = $normalizer->normalize(['name' => 'example.txt']);

        $this->assertFalse($result->stable);
    }
}
