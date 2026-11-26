<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Facades\AiJournal;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiJournal\Tests\TestCase;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

final class ErrorMetadataTest extends TestCase
{
    /**
     * HTTP error metadata is stored on the invocation, step, and attempt.
     */
    public function test_http_error_metadata_is_recorded_everywhere(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000051';
        $prompt = $this->dispatchPrompting($invocationId);
        $exception = new \RuntimeException('Wrapped provider failure.', 0, $this->httpException(429, [
            'error' => ['code' => 'rate_limited', 'message' => 'Too many requests'],
            'token' => 'must-not-be-stored',
        ], [
            'Retry-After' => '30',
            'X-Request-Id' => 'provider-request-1',
        ]));

        $this->dispatchFailedStep($invocationId, $prompt, $exception);
        $this->dispatchFailed($invocationId, $prompt, $exception);

        $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();
        $step = AiInvocationStep::query()->where('ai_invocation_id', $invocation->id)->firstOrFail();

        foreach ([$invocation, $step] as $record) {
            $this->assertSame('rate_limited', $record->error_code);
            $this->assertSame(429, $record->http_status);
            $this->assertSame('provider-request-1', $record->provider_request_id);
            $this->assertSame(30, $record->retry_after);
            $this->assertArrayNotHasKey('token', $record->error_details['body']);
            $this->assertSame('rate_limited', $record->error_details['body']['error']['code']);
        }

        $this->assertSame('rate_limited', $invocation->attempts[0]['error_code']);
        $this->assertSame(429, $invocation->attempts[0]['http_status']);
        $this->assertSame('provider-request-1', $invocation->attempts[0]['provider_request_id']);
        $this->assertSame(30, $invocation->attempts[0]['retry_after']);
        $this->assertArrayNotHasKey('token', $invocation->attempts[0]['error_details']['body']);

        $page = AiJournal::invocations(['error_code' => 'rate_limited']);

        $this->assertSame(1, $page->total());
    }

    /**
     * Errors without an HTTP response keep null machine fields.
     */
    public function test_non_http_error_keeps_machine_fields_null(): void
    {
        $invocationId = '0190b4a0-0000-7000-8000-000000000052';
        $prompt = $this->dispatchPrompting($invocationId);

        $this->dispatchFailedStep($invocationId, $prompt, new \RuntimeException('No HTTP response.'));
        $this->dispatchFailed($invocationId, $prompt, new \RuntimeException('No HTTP response.'));

        $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();
        $step = AiInvocationStep::query()->where('ai_invocation_id', $invocation->id)->firstOrFail();

        $this->assertNull($invocation->error_code);
        $this->assertNull($invocation->http_status);
        $this->assertNull($step->error_code);
        $this->assertNull($step->http_status);
        $this->assertEqualsCanonicalizing(
            ['error_class', 'error_code', 'error_details', 'error_message', 'finished_at', 'http_status', 'model', 'number', 'provider', 'provider_request_id', 'retry_after', 'started_at', 'status'],
            array_keys($invocation->attempts[0]),
        );
    }

    /**
     * Other HTTP statuses are recorded without Retry-After.
     */
    public function test_other_http_statuses_are_recorded(): void
    {
        foreach ([500, 400] as $index => $status) {
            $invocationId = '0190b4a0-0000-7000-8000-00000000006'.($index + 3);
            $prompt = $this->dispatchPrompting($invocationId);
            $exception = $this->httpException($status, ['error' => ['code' => 'request_failed']]);

            $this->dispatchFailedStep($invocationId, $prompt, $exception);
            $this->dispatchFailed($invocationId, $prompt, $exception);

            $invocation = AiInvocation::query()->where('invocation_id', $invocationId)->firstOrFail();

            $this->assertSame($status, $invocation->http_status);
            $this->assertNull($invocation->retry_after);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function httpException(int $status, array $body, array $headers = []): RequestException
    {
        return new RequestException(new Response(new PsrResponse(
            $status,
            [...$headers, 'Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        )));
    }
}
