<?php

namespace Goodport\AiJournal\Journal\Replay;

use Goodport\AiJournal\Journal\Recording\PayloadNormalizer;
use JsonException;

final class StepFingerprint
{
    public function __construct(private PayloadNormalizer $normalizer) {}

    public function fromPayload(StepPayload $payload): ?string
    {
        if (! $payload->stable) {
            return null;
        }

        try {
            $json = json_encode(
                $this->canonicalize($this->normalizer->normalize($payload->fingerprintData)),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException) {
            return null;
        }

        return hash('sha256', $json);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        $canonical = [];

        foreach ($value as $key => $item) {
            $canonical[$key] = $this->canonicalize($item);
        }

        return $canonical;
    }
}
