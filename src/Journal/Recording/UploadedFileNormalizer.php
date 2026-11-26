<?php

namespace Goodport\AiJournal\Journal\Recording;

use Illuminate\Http\UploadedFile as IlluminateUploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Throwable;

final class UploadedFileNormalizer
{
    /**
     * Normalize a real uploaded file without storing binary content or temporary paths.
     */
    public function normalize(IlluminateUploadedFile|SymfonyUploadedFile $file): NormalizedPayload
    {
        $originalName = $file->getClientOriginalName();
        $data = [
            'type' => 'uploaded-file',
            'name' => $originalName,
            'original_name' => $originalName,
            'mime' => $this->mimeType($file),
            'size' => null,
            'sha256' => null,
        ];

        if (! $file->isValid()) {
            return new NormalizedPayload($data, false);
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            return new NormalizedPayload($data, false);
        }

        try {
            $hash = hash_file('sha256', $path);
            $size = filesize($path);
        } catch (Throwable) {
            return new NormalizedPayload($data, false);
        }

        if (! is_string($hash) || $hash === '' || $size === false) {
            return new NormalizedPayload($data, false);
        }

        $data['sha256'] = $hash;
        $data['size'] = $size;

        return new NormalizedPayload($data, true);
    }

    private function mimeType(SymfonyUploadedFile $file): ?string
    {
        try {
            $mime = $file->getMimeType();
        } catch (Throwable) {
            $mime = null;
        }

        return $mime ?: $file->getClientMimeType();
    }
}
