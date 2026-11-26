<?php

namespace Goodport\AiJournal\Journal\Recording;

use Goodport\AiJournal\Journal\Attachments\RemoteAttachmentHasher;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\UploadedFile as IlluminateUploadedFile;
use JsonSerializable;
use Laravel\Ai\Files\File;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Throwable;

final class AttachmentNormalizer
{
    public function __construct(
        private PayloadNormalizer $normalizer,
        private UploadedFileNormalizer $uploadedFiles,
        private ?JournalConfig $config = null,
    ) {}

    /**
     * Normalize an attachment into metadata without binary content.
     */
    public function normalize(mixed $attachment): NormalizedPayload
    {
        if ($attachment === null) {
            return new NormalizedPayload([], true);
        }

        if ($attachment instanceof IlluminateUploadedFile || $attachment instanceof SymfonyUploadedFile) {
            return $this->uploadedFiles->normalize($attachment);
        }

        if ($attachment instanceof File) {
            return $this->normalizeFile($attachment);
        }

        if ($attachment instanceof Arrayable) {
            return $this->normalizeArray($this->normalizer->normalizeArray($attachment->toArray()));
        }

        if ($attachment instanceof JsonSerializable) {
            return $this->normalizeArray($this->normalizer->normalizeArray((array) $attachment->jsonSerialize()));
        }

        if (is_array($attachment)) {
            return $this->normalizeArray($this->normalizer->normalizeArray($attachment));
        }

        if (is_string($attachment) || is_scalar($attachment)) {
            return new NormalizedPayload(['value' => $attachment], true);
        }

        return new NormalizedPayload(['class' => $attachment::class], false);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function normalizeArray(array $data): NormalizedPayload
    {
        $type = is_string($data['type'] ?? null) ? $data['type'] : null;

        if ($this->isRemote($data, $type)) {
            return $this->normalizeRemote($data);
        }

        $stable = $this->hasStableReference($data, $type);

        if (isset($data['base64'])) {
            unset($data['base64']);
            $stable = isset($data['base64_hash']);
        }

        return new NormalizedPayload($data, $stable);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function normalizeRemote(array $data): NormalizedPayload
    {
        $url = $data['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return new NormalizedPayload($data, false);
        }

        $mode = $this->config?->remoteAttachmentMode ?? 'never';

        if ($mode === 'trust') {
            return new NormalizedPayload($data, true);
        }

        if ($mode !== 'hash') {
            return new NormalizedPayload($data, false);
        }

        try {
            $hasher = app($this->config->remoteAttachmentHasher);
            $hash = $hasher instanceof RemoteAttachmentHasher ? $hasher->hash($url) : null;
        } catch (Throwable) {
            $hash = null;
        }

        if (! is_string($hash) || $hash === '') {
            return new NormalizedPayload($data, false);
        }

        $data['sha256'] = $hash;

        return new NormalizedPayload($data, true);
    }

    private function normalizeFile(File $file): NormalizedPayload
    {
        $data = $this->normalizer->normalizeArray($file->toArray());
        $type = is_string($data['type'] ?? null) ? $data['type'] : null;

        if ($type !== null && str_starts_with($type, 'base64-') && method_exists($file, 'content')) {
            $content = $file->content();

            $data['base64_hash'] = hash('sha256', $content);
            $data['size'] = strlen($content);
            unset($data['base64']);

            return new NormalizedPayload($data, true);
        }

        if (isset($data['base64'])) {
            unset($data['base64']);
        }

        if ($this->isRemote($data, $type)) {
            return $this->normalizeRemote($data);
        }

        if ($type !== null && str_starts_with($type, 'local-') && isset($data['path']) && is_string($data['path']) && is_readable($data['path'])) {
            $hash = hash_file('sha256', $data['path']);

            if (is_string($hash)) {
                $data['hash'] = $hash;
                $data['size'] = filesize($data['path']);

                return new NormalizedPayload($data, true);
            }
        }

        return new NormalizedPayload($data, $this->hasStableReference($data, $type));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function isRemote(array $data, ?string $type): bool
    {
        return is_string($data['url'] ?? null)
            && ($type === null || str_starts_with($type, 'remote-'));
    }

    private function hasStableReference(array $data, ?string $type): bool
    {
        foreach (['id', 'hash', 'sha256', 'base64_hash'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return true;
            }
        }

        if ($type !== null && (str_starts_with($type, 's3-') || str_starts_with($type, 'provider-'))) {
            return true;
        }

        return false;
    }
}
