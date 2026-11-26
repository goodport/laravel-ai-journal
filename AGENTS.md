# AGENTS.md

Technical description of `goodport/laravel-ai-journal`.

## Purpose

The package records agent text calls made through `laravel/ai` and can replay a successful generation step.

The flow is event-based:

1. `PromptingAgent` / `StreamingAgent` creates or updates an invocation.
2. `StartingStep` creates a step with a normalized request and fingerprint.
3. `StepCompleted` / `StepFailed` stores the raw step outcome.
4. `AgentPrompted` / `AgentStreamed` finalizes the invocation.
5. `AgentFailed` stores a terminal error.
6. `AgentFailedOver` records a failed attempt without closing the invocation.

Replay is independent of recording. It is an agent middleware that receives a `PendingStep`, finds a matching successful step, and returns its `StepResponse`.

## Directory map

```text
config/ai-journal.php
database/migrations/
  2026_11_26_000000_create_ai_invocations_table.php
  2026_11_26_000001_create_ai_invocation_steps_table.php
  2026_11_26_000002_add_error_metadata_to_ai_journal_tables.php
src/
  AiJournalServiceProvider.php
  AiJournalManager.php
  Facades/AiJournal.php
  Console/PruneJournalCommand.php
  Enums/
    AttemptStatus.php
    InvocationStatus.php
    StepStatus.php
  Models/
    AiInvocation.php
    AiInvocationStep.php
  Journal/
    Attachments/      remote attachment hasher contract
    Config/           config value object, validator, exception
    Context/          Laravel Context reader
    Errors/           HTTP error metadata extraction and sanitization
    Cost/             usage-to-cost calculator
    Listeners/        laravel/ai event subscriber
    Pruning/          retention service
    Recording/        invocation factory/finalizer, recorders, attempt tracker, normalizers
    Redaction/        redactor contract and recursive payload redactor
    Replay/           fingerprint, payload factory, response mapper, middleware
tests/
  Feature/            event, replay, pruning, failure and config flows
  Unit/               fingerprint, mapper, cost and normalization
  Fixtures/           redactor and structured-agent fixtures
```

## Data model

`ai_invocations` is one logical agent run.

- `invocation_id` comes from `laravel/ai` and is unique.
- `attempt_count` and `attempts` keep provider failover history. Attempt entries have a fixed key set including `error_code`, `http_status`, `provider_request_id`, `retry_after`, and `error_details`.
- `status` is `pending`, `pending_approval`, `succeeded`, or `failed`.
- `streamed` and `replayed` are summary flags.
- `trace_id`, `scope`, `context`, and `tags` carry correlation data.
- `cost` is calculated from step costs; an unknown successful step cost makes the total `null`.

`ai_invocation_steps` is one generation step inside an attempt.

- Unique key: `(ai_invocation_id, attempt_number, step_number)`.
- `request_fingerprint` is a SHA-256 of the canonical request.
- `request` is a redacted audit snapshot; `response` is the normalized raw `StepResponse`.
- invocations and steps keep normalized HTTP error metadata; attempts keep the same keys in JSON.
- `cost` is zero for a replayed step.
- `replay_of_step_id` links a replay to its source.

The attempt dimension is required because failover restarts the provider loop with the same invocation ID and step number zero.

## Recording

`JournalEventSubscriber` subscribes to both base and streaming event classes.

`InvocationRecorder` orchestrates invocation lifecycle. `InvocationFactory` creates and fills records, `AttemptTracker` maintains attempt history, and `InvocationFinalizer` applies terminal responses and errors. `InvocationStepRecorder` handles steps. `AttemptRegistry` remembers the current attempt per invocation for the duration of a run.

Listeners are synchronous and fail-open by default:

```php
try {
    // write
} catch (Throwable $exception) {
    report($exception);

    if ($config->throw) {
        throw $exception;
    }
}
```

## Fingerprint and replay

`StepPayloadFactory` builds a request payload from either `StartingStep` or `PendingStep`.

At step start the package reconstructs instructions and schema from the agent because `StartingStep` does not expose them. Tool names are included; tool-free agents produce the same payload at start and replay. For agents with tools, replay is disabled by default.

`StepFingerprint` canonicalizes associative arrays recursively and hashes the JSON with SHA-256. It includes:

- agent class, provider, model, instructions, messages, tools, schema, options;
- `is_final_step`;
- configured `Context` keys; the default list contains `ai.check` and `ai.pass`;
- replay scope.

`ReplayRecordedStep` only runs when:

- replay is enabled and the effective TTL is positive;
- `ai.journal.fresh` is not `true`; the effective TTL can come from `ai.journal.ttl`;
- response capture is enabled and no redactor is configured;
- the step is the first step and has no tools, unless explicitly allowed;
- the stored response has no tool calls, no pending approvals, no citations, and a replayable finish reason;
- a successful source exists in the same scope and TTL.

The replay step is recorded with `status = replayed`, `cost = 0`, and a source link. The original usage is kept for reference.

## Configuration

`ConfigValidator` validates the published config on first use and throws `InvalidConfigurationException` for invalid values. There are no fallback values for required configuration.

`JournalConfig` is a readonly value object. It is bound as a singleton, so config changes after the first resolution are not expected.

`Redactor` and `ReplayScope` are container contracts. The default redactor is a no-op; the default scope reads its value from `Context`.

Remote attachment replay is controlled by `attachments.remote.mode`: `never` (default), `hash`, or `trust`. `hash` requires a class implementing `RemoteAttachmentHasher`; a missing hasher is a configuration error. The hasher is application-owned and must enforce its own timeout, size limit, and host allowlist. If hashing fails, the attachment is unstable and replay is skipped.

## Public API

- `AiJournal::invocation()`
- `AiJournal::trace()`
- `AiJournal::invocations()` with filters and pagination
- `AiJournal::prune()`

Models are public for application-specific query screens. The list API selects summary columns only and does not load prompt or response payloads. The package does not include a UI or admin panel.

## Boundaries

The package records agent text generation only. Embeddings, images, audio, transcription, classification, and reranking use different `laravel/ai` events and are not part of this schema.

The package does not:

- store binary attachments;
- use queues for journal writes;
- provide a distributed lock against identical concurrent calls;
- encrypt the journal;
- calculate prices without configured rates;
- schedule pruning automatically.

## Tests

```bash
composer test
composer format
```

Feature tests use SQLite in memory, Testbench, fake agents, and Laravel event classes. No network calls are made.
