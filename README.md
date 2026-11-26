# Laravel AI Journal

Journal and response replay for Laravel AI agent calls.

The package listens to `laravel/ai` events and stores each agent invocation and generation step: prompt, response, usage, cost, errors, failover attempts, and correlation context. It can also replay a previously successful step without calling the provider.

It does not require a specific application model. Correlation is passed through Laravel `Context`, and replay is an explicit agent middleware.

## Requirements

- PHP 8.3+
- Laravel 12.62+ or 13.x
- `laravel/ai` 1.4+

## Installation

```bash
composer require goodport/laravel-ai-journal
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=ai-journal-config
```

Run migrations:

```bash
php artisan migrate
```

The package loads its migrations automatically.

## What Is Stored

The package creates two tables:

- `ai_invocations` — one agent run;
- `ai_invocation_steps` — one generation step inside an attempt.

An invocation can contain several attempts because `laravel/ai` keeps the same invocation ID during provider failover. Steps are therefore keyed by invocation, attempt, and step number.

Recorded fields include:

- the agent class, provider, model, and status;
- the original prompt and response;
- structured output, when present;
- usage and calculated cost;
- failover attempts and their errors;
- a normalized request per step and a stable request fingerprint;
- the raw step response needed for safe replay;
- trace ID, scope, tags, and a snapshot of Laravel `Context`.

Binary attachments are not stored. Attachment metadata contains a name, MIME type, size, URL, provider ID, or content hash. A local or base64 file is hashed so the same file can be recognized later.

## Reading the Journal

Use the facade for common lookups:

```php
use Goodport\AiJournal\Facades\AiJournal;

$invocation = AiJournal::invocation($invocationId);
$invocations = AiJournal::trace($traceId);
```

Models are public and can be queried directly:

```php
use Goodport\AiJournal\Models\AiInvocation;

$invocations = AiInvocation::query()
    ->where('agent', PostModerationAgent::class)
    ->latest()
    ->paginate();
```

`AiInvocation` has a `steps()` relation. Each step keeps its attempt number, request fingerprint, normalized request and response, usage, cost, and replay source.

## Correlation

The package reads Laravel `Context`:

- `ai.trace_id` — stored in the `trace_id` column;
- `ai.journal.scope` — isolation scope for replay;
- `ai.journal.tags` — arbitrary tags;
- `ai.journal.fresh` — when `true`, bypass replay for the current call;
- `ai.journal.ttl` — optional replay TTL override in seconds;
- the rest of `Context::all()` — stored in `context`, when capture is enabled.

```php
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

Context::add([
    'ai.trace_id' => (string) Str::uuid(),
    'ai.journal.scope' => 'tenant-a',
    'ai.journal.tags' => ['feature' => 'post-moderation'],
    'ai.journal.ttl' => 3600,
]);
```

The package does not import application or domain classes. Any value the application puts into `Context` is available for correlation.

## Replay

Replay is disabled by default. Enable it in the config:

```php
'replay' => [
    'enabled' => true,
    'ttl' => 3600,
    'scope' => Goodport\AiJournal\Journal\Replay\ContextReplayScope::class,
    'fingerprint_context' => ['ai.check', 'ai.pass'],
    'allow_tools' => false,
    'first_step_only' => true,
],
```

Attach `ReplayRecordedStep` to an agent that implements `Laravel\Ai\Contracts\HasMiddleware`:

```php
use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;

public function middleware(): array
{
    return [
        ReplayRecordedStep::class,
    ];
}
```

When a matching successful step exists within the TTL and scope, the middleware returns its `StepResponse` and no provider call is made. The new step is stored as `replayed` and links to the original through `replay_of_step_id`.

Replay is deliberately conservative:

- only successful steps;
- only the first step by default;
- no steps with tools by default;
- no tool calls or pending tool approvals;
- only `stop`, `length`, and `content_filter` finish reasons;
- no responses with citations;
- no replay when a redactor is configured;
- no replay when response capture is disabled;
- no replay for un-fingerprintable requests.

If lookup or reconstruction fails, the provider is called normally.

### Scope and separate workloads

`scope` separates replay entries. A multi-tenant application should return the tenant key from a custom `ReplayScope` implementation, or put the tenant into `ai.journal.scope`.

The default fingerprint context includes `ai.check` and `ai.pass`, so separate check types and second passes do not reuse each other's responses. Add more keys when the application has its own separation rules:

```php
'fingerprint_context' => ['ai.check', 'ai.pass', 'tenant.id'],
```

Use `ai.journal.fresh` when a single call must ignore the stored response:

```php
Context::add('ai.journal.fresh', true);
```

`ai.journal.ttl` overrides the configured TTL for the current call.

## Cost

Cost is optional. Rates are configured per provider and model, per one million tokens:

```php
'cost' => [
    'currency' => 'USD',
    'providers' => [
        'openai' => [
            'models' => [
                'example-model' => [
                    'input' => 0.15,
                    'output' => 0.60,
                    'cache_read' => 0.075,
                    'cache_write' => 0.30,
                ],
            ],
        ],
    ],
],
```

`cache_read` and `cache_write` fall back to `input` when omitted. If no rate exists for a model, the stored cost is `null`; the package does not guess.

A replayed step stores `cost = 0` and keeps the original usage for reference.

## Privacy

Prompts, responses, context, and step payloads can contain personal data. The package stores them only when enabled.

Use a `Redactor` for stored text:

```php
use Goodport\AiJournal\Journal\Redaction\Redactor;

final class ApplicationRedactor implements Redactor
{
    public function redact(string $value): string
    {
        return preg_replace('/\b[\w.+-]+@[\w.-]+\.\w+\b/', '[email]', $value) ?? $value;
    }
}
```

```php
'redactor' => ApplicationRedactor::class,
```

Redaction applies to stored payloads. The request fingerprint is computed before redaction. Replay is disabled when a redactor is configured, because a redacted response cannot be returned as the original AI answer.

Disable individual capture groups when text is not needed:

```php
'capture' => [
    'prompt' => true,
    'response' => true,
    'step_request' => true,
    'attachments' => 'metadata', // none | metadata; none prevents replay for steps with attachments
    'context' => true,
],
```

Disabling responses also disables replay.

## Pruning

```bash
php artisan ai-journal:prune --days=30
php artisan ai-journal:prune --days=30 --dry-run
```

The command deletes invocations older than the cutoff and their steps. The package does not schedule itself; add the command to the application scheduler when needed.

## Failure Handling

Journaling must not break the AI call. Write and replay errors are reported through Laravel's `report()` and the call continues.

For tests or debugging, set:

```php
'throw' => true,
```

Then journal errors are rethrown.

## Testing

```bash
composer test
composer format
```

## License

MIT. See `LICENSE`.
