<?php

namespace Goodport\AiJournal\Journal\Recording;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\UploadedFile as IlluminateUploadedFile;
use JsonSerializable;
use Laravel\Ai\Files\File;
use Stringable;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use UnitEnum;

final class PayloadNormalizer
{
    public function __construct(private UploadedFileNormalizer $uploadedFiles) {}

    /**
     * Normalize a value into a JSON-safe representation.
     */
    public function normalize(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value), is_float($value), is_string($value) => $value,
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof Closure => ['class' => Closure::class],
            is_resource($value) => ['class' => 'resource'],
            $value instanceof IlluminateUploadedFile || $value instanceof SymfonyUploadedFile => $this->uploadedFiles->normalize($value)->data,
            $value instanceof File => $this->normalizeFile($value),
            is_array($value) => $this->normalizeArray($value),
            $value instanceof Arrayable => $this->normalize($value->toArray()),
            $value instanceof JsonSerializable => $this->normalize($value->jsonSerialize()),
            method_exists($value, 'toArray') => $this->normalize($value->toArray()),
            $value instanceof Stringable => (string) $value,
            is_object($value) => $this->normalizeObject($value),
            default => (string) $value,
        };
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    public function normalizeArray(array $value): array
    {
        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalize($item);
        }

        return $normalized;
    }

    /**
     * Determine whether the value contains data that cannot be compared stably.
     */
    public function containsUnstable(mixed $value): bool
    {
        if ($value instanceof Closure || is_resource($value)) {
            return true;
        }

        if ($value instanceof IlluminateUploadedFile || $value instanceof SymfonyUploadedFile) {
            return ! $this->uploadedFiles->normalize($value)->stable;
        }

        if ($value instanceof File) {
            return false;
        }

        if ($value instanceof Arrayable) {
            return $this->containsUnstable($value->toArray());
        }

        if ($value instanceof JsonSerializable) {
            return $this->containsUnstable($value->jsonSerialize());
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsUnstable($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $this->containsUnstable($value->toArray());
        }

        if (is_object($value) && ! $value instanceof UnitEnum && ! $value instanceof DateTimeInterface) {
            foreach (get_object_vars($value) as $item) {
                if ($this->containsUnstable($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeFile(File $file): array
    {
        $data = $this->normalizeArray($file->toArray());

        if (array_key_exists('base64', $data) && method_exists($file, 'content')) {
            $content = $file->content();

            $data['base64_hash'] = hash('sha256', $content);
            $data['size'] = strlen($content);
        }

        unset($data['base64']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeObject(object $value): array
    {
        $vars = get_object_vars($value);

        if ($vars !== []) {
            return $this->normalizeArray($vars);
        }

        return ['class' => $value::class];
    }
}
