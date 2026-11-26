<?php

namespace Goodport\AiJournal\Journal\Recording;

use Goodport\AiJournal\Enums\InvocationStatus;
use Goodport\AiJournal\Journal\Errors\ErrorMetadataExtractor;
use Goodport\AiJournal\Journal\Redaction\PayloadRedactor;
use Goodport\AiJournal\Models\AiInvocation;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\PendingStep;

final class InvocationRecorder
{
    public function __construct(
        private InvocationFactory $factory,
        private AttemptTracker $attempts,
        private InvocationFinalizer $finalizer,
        private PayloadRedactor $redactor,
        private ErrorMetadataExtractor $errors,
    ) {}

    public function start(PromptingAgent|StreamingAgent $event): void
    {
        $prompt = $event->prompt;
        $invocation = $this->factory->find($prompt->invocationId)
            ?? $this->factory->new(
                $prompt->invocationId,
                $prompt->agent,
                $prompt->provider->name(),
                $prompt->model,
            );

        $this->factory->fill(
            $invocation,
            $prompt->agent,
            $prompt->provider->name(),
            $prompt->model,
        );
        $this->factory->capturePrompt($invocation, $prompt);

        $this->attempts->start(
            $invocation,
            $prompt->provider->name(),
            $prompt->model,
            now(),
        );

        $invocation->status = InvocationStatus::Pending;
        $invocation->streamed = (bool) $invocation->streamed || $event instanceof StreamingAgent;
        $invocation->finished_at = null;
        $invocation->error_class = null;
        $invocation->error_message = null;
        $invocation->error_code = null;
        $invocation->http_status = null;
        $invocation->provider_request_id = null;
        $invocation->retry_after = null;
        $invocation->error_details = null;
        $invocation->save();
    }

    public function succeed(AgentPrompted|AgentStreamed $event): void
    {
        $invocation = $this->factory->ensureFromPrompt($event->prompt);
        $attempt = $this->attempts->current($invocation);

        $this->finalizer->succeed(
            $invocation,
            $event->response,
            $event instanceof AgentStreamed,
            $attempt,
            now(),
        );
    }

    public function fail(AgentFailed $event): void
    {
        $invocation = $this->factory->ensureFromPrompt($event->prompt);
        $attempt = $this->attempts->current($invocation);

        $this->finalizer->fail(
            $invocation,
            $event->exception,
            $attempt,
            now(),
        );
    }

    public function failover(AgentFailedOver $event): void
    {
        $invocation = $this->factory->ensureFromFailedOver($event);
        $attempt = $this->attempts->current($invocation);
        $finishedAt = now();

        $invocation->provider = $event->provider->name();
        $invocation->model = $event->model;
        $invocation->status = InvocationStatus::Pending;

        $this->attempts->failover(
            $invocation,
            $attempt,
            $event->provider->name(),
            $event->model,
            $event->exception::class,
            $this->redactor->redactString($event->exception->getMessage()),
            $this->errors->extract($event->exception),
            $finishedAt,
        );
        $invocation->save();
    }

    public function ensureForStartingStep(StartingStep $event): AiInvocation
    {
        return $this->factory->ensureForStartingStep($event);
    }

    public function ensureForPendingStep(PendingStep $step): AiInvocation
    {
        return $this->factory->ensureForPendingStep($step);
    }

    public function ensureForStep(
        string $invocationId,
        Agent $agent,
        string $provider,
        string $model,
    ): AiInvocation {
        return $this->factory->ensureForStep($invocationId, $agent, $provider, $model);
    }

    public function attemptFor(AiInvocation $invocation): int
    {
        return $this->attempts->current($invocation);
    }
}
