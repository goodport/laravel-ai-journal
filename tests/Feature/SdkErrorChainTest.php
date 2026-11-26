<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Tests\TestCase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;

final class SdkErrorChainTest extends TestCase
{
    /**
     * The SDK keeps the HTTP RequestException inside a rate-limit exception.
     */
    public function test_rate_limit_exception_keeps_http_exception_in_chain(): void
    {
        $this->configureProvider();
        Http::fake([
            'api.example.com/*' => Http::response(['error' => ['code' => 'rate_limited']], 429, [
                'Retry-After' => '30',
                'X-Request-Id' => 'request-429',
            ]),
        ]);

        try {
            $this->promptProvider();
            $this->fail('RateLimitedException was not thrown.');
        } catch (RateLimitedException $exception) {
            $this->assertInstanceOf(RequestException::class, $exception->getPrevious());
            $this->assertSame(429, $exception->getPrevious()->response->status());
        }
    }

    /**
     * A 500 response is a RequestException that the extractor can read directly.
     */
    public function test_server_error_is_a_request_exception(): void
    {
        $this->configureProvider();
        Http::fake([
            'api.example.com/*' => Http::response(['error' => ['code' => 'server_error']], 500),
        ]);

        $this->expectException(RequestException::class);

        $this->promptProvider();
    }

    private function configureProvider(): void
    {
        $this->app['config']->set('ai.providers.openai-compatible.url', 'https://api.example.com/v1');
        $this->app['config']->set('ai.providers.openai-compatible.key', 'test-key');
    }

    private function promptProvider(): void
    {
        $provider = Ai::textProvider('openai-compatible');
        $agent = new AnonymousAgent('Instructions', [], []);

        $provider->prompt(new AgentPrompt(
            agent: $agent,
            prompt: 'Hello',
            attachments: [],
            provider: $provider,
            model: 'gpt-test',
            invocationId: '0190b4a0-0000-7000-8000-000000000081',
        ));
    }
}
