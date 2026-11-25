<?php

namespace Goodport\AiJournal\Journal\Recording;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Laravel\Ai\Files\File;

final class AttachmentNormalizer
{
    public function __construct(private PayloadNormalizer $normalizer) {}

    /**
     * Normalize an attachment into metadata without binary content.
     */
    public function normalize(mixed $attachment): NormalizedPayload
    {
        if ($attachment === null) {
            return new NormalizedPayload([], true);
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
        $stable = $this->hasStableReference($data, $type);

        if (isset($data['base64'])) {
            unset($data['base64']);
            $stable = isset($data['base64_hash']);
        }

        return new NormalizedPayload($data, $stable);
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
    private function hasStableReference(array $data, ?string $type): bool
    {
        foreach (['url', 'id', 'hash', 'base64_hash'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return true;
            }
        }

        if ($type !== null && (str_starts_with($type, 'remote-') || str_starts_with($type, 's3-') || str_starts_with($type, 'provider-'))) {
            return true;
        }

        return false;
    }
}
