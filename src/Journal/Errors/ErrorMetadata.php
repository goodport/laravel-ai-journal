<?php

namespace Goodport\AiJournal\Journal\Errors;

final readonly class ErrorMetadata
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        public ?string $errorCode = null,
        public ?int $httpStatus = null,
        public ?string $providerRequestId = null,
        public ?int $retryAfter = null,
        public ?array $details = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }
}
