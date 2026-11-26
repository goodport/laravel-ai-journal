<?php

namespace Goodport\AiJournal\Tests\Unit\Journal\Recording;

use Goodport\AiJournal\Journal\Recording\AttachmentNormalizer;
use Goodport\AiJournal\Journal\Recording\PayloadNormalizer;
use Goodport\AiJournal\Journal\Recording\UploadedFileNormalizer;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Files\RemoteImage;
use PHPUnit\Framework\TestCase;

final class AttachmentNormalizerTest extends TestCase
{
    public function test_base64_attachment_is_stored_as_metadata(): void
    {
        $normalizer = new AttachmentNormalizer(new PayloadNormalizer(new UploadedFileNormalizer), new UploadedFileNormalizer);
        $result = $normalizer->normalize(new Base64Image(base64_encode('image-content'), 'image/png'));

        $this->assertTrue($result->stable);
        $this->assertArrayNotHasKey('base64', $result->data);
        $this->assertSame(hash('sha256', 'image-content'), $result->data['base64_hash']);
        $this->assertSame(strlen('image-content'), $result->data['size']);
    }

    public function test_attachment_without_stable_reference_is_not_stable(): void
    {
        $normalizer = new AttachmentNormalizer(new PayloadNormalizer(new UploadedFileNormalizer), new UploadedFileNormalizer);
        $result = $normalizer->normalize(['name' => 'example.txt']);

        $this->assertFalse($result->stable);
    }

    public function test_remote_url_is_not_stable_by_default(): void
    {
        $normalizer = new AttachmentNormalizer(new PayloadNormalizer(new UploadedFileNormalizer), new UploadedFileNormalizer);
        $result = $normalizer->normalize(new RemoteImage('https://example.com/avatar.png'));

        $this->assertFalse($result->stable);
    }

    public function test_uploaded_file_is_normalized_with_content_hash(): void
    {
        $file = UploadedFile::fake()->createWithContent('avatar.png', 'image-content');

        $normalizer = new AttachmentNormalizer(new PayloadNormalizer(new UploadedFileNormalizer), new UploadedFileNormalizer);
        $result = $normalizer->normalize($file);

        $this->assertTrue($result->stable);
        $this->assertSame('uploaded-file', $result->data['type']);
        $this->assertSame('avatar.png', $result->data['original_name']);
        $this->assertSame('image/png', $result->data['mime']);
        $this->assertSame(13, $result->data['size']);
        $this->assertSame(hash('sha256', 'image-content'), $result->data['sha256']);
        $this->assertArrayNotHasKey('path', $result->data);
    }

    public function test_deleted_uploaded_file_is_not_stable(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ai-journal-');
        file_put_contents($path, 'image-content');
        $file = new UploadedFile($path, 'avatar.png', 'image/png', null, true);
        unlink($path);

        $normalizer = new AttachmentNormalizer(new PayloadNormalizer(new UploadedFileNormalizer), new UploadedFileNormalizer);
        $result = $normalizer->normalize($file);

        $this->assertFalse($result->stable);
        $this->assertNull($result->data['sha256']);
    }
}
