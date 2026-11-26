<?php

namespace Goodport\AiJournal\Journal\Errors;

use Goodport\AiJournal\Journal\Redaction\PayloadRedactor;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

final class ErrorMetadataExtractor
{
    public function __construct(private PayloadRedactor $redactor) {}

    /**
     * Extract machine-readable metadata from an HTTP exception chain.
     */
    public function extract(Throwable $exception): ErrorMetadata
    {
        $requestException = $this->requestException($exception);

        if ($requestException === null || $requestException->response === null) {
            return ErrorMetadata::empty();
        }

        $response = $requestException->response;
        $body = $this->body($response);
        $details = $body === null ? null : ['body' => $this->sanitize($body)];

        return new ErrorMetadata(
            errorCode: $this->errorCode($body),
            httpStatus: $response->status(),
            providerRequestId: $this->providerRequestId($response),
            retryAfter: $this->retryAfter($response->header('Retry-After')),
            details: $details,
        );
    }

    private function requestException(Throwable $exception): ?RequestException
    {
        $current = $exception;

        while ($current !== null) {
            if ($current instanceof RequestException) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function body(Response $response): array|string|null
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        $body = $response->body();

        return $body === '' ? null : $body;
    }

    /**
     * @param  array<string, mixed>|string|null  $body
     */
    private function errorCode(array|string|null $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $candidates = [
            $body['error']['code'] ?? null,
            $body['error']['type'] ?? null,
            $body['code'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }

            if (is_int($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function providerRequestId(Response $response): ?string
    {
        foreach (['x-request-id', 'request-id', 'x-amzn-requestid', 'x-goog-request-id'] as $header) {
            $value = $response->header($header);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function retryAfter(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->redactor->redactString($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = $this->sanitize($item);
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, ['authorization', 'api_key', 'apikey', 'x-api-key', 'token', 'secret', 'password', 'cookie', 'set-cookie'], true)) {
            return true;
        }

        return str_ends_with($key, '_token') || str_ends_with($key, '_secret') || str_ends_with($key, '_password');
    }
}
