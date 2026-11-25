<?php

namespace Goodport\AiJournal\Journal\Recording;

use Goodport\AiJournal\Enums\InvocationStatus;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Journal\Context\ContextReader;
use Goodport\AiJournal\Journal\Redaction\PayloadRedactor;
use Goodport\AiJournal\Journal\Replay\ReplayScope;
use Goodport\AiJournal\Models\AiInvocation;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;
use LogicException;

final class InvocationFactory
{
    public function __construct(
        private JournalConfig $config,
        private ContextReader $context,
        private ReplayScope $scope,
        private AttachmentNormalizer $attachments,
        private PayloadRedactor $redactor,
        private AttemptTracker $attempts,
    ) {}

    public function find(string $invocationId): ?AiInvocation
    {
        return AiInvocation::query()
            ->where('invocation_id', $invocationId)
            ->first();
    }

    public function new(string $invocationId, Agent $agent, string $provider, string $model): AiInvocation
    {
        $startedAt = now();
        $invocation = new AiInvocation;
        $invocation->invocation_id = $invocationId;
        $invocation->agent = $agent::class;
        $invocation->provider = $provider;
        $invocation->model = $model;
        $invocation->status = InvocationStatus::Pending;
        $invocation->streamed = false;
        $invocation->replayed = false;
        $invocation->attempt_count = 1;
        $invocation->attempts = $this->attempts->initial($provider, $model, $startedAt);
        $invocation->trace_id = $this->context->traceId();
        $invocation->scope = $this->scope->current();
        $invocation->context = $this->context->context();
        $invocation->tags = $this->context->tags();
        $invocation->started_at = $startedAt;

        return $invocation;
    }

    public function fill(
        AiInvocation $invocation,
        ?Agent $agent,
        string $provider,
        string $model,
    ): void {
        if ($agent !== null) {
            $invocation->agent = $agent::class;
        }

        $invocation->provider ??= $provider;
        $invocation->model ??= $model;
        $invocation->started_at ??= now();
        $invocation->trace_id ??= $this->context->traceId();
        $invocation->scope ??= $this->scope->current();
        $invocation->context ??= $this->context->context();
        $invocation->tags ??= $this->context->tags();
        $invocation->attempt_count = max(1, (int) $invocation->attempt_count);
    }

    public function capturePrompt(AiInvocation $invocation, AgentPrompt $prompt): void
    {
        if ($this->config->capturePrompt && $invocation->prompt === null) {
            $invocation->prompt = $this->redactor->redactString($prompt->prompt);
        }

        if ($invocation->attachments === null) {
            $invocation->attachments = $this->attachments($prompt);
        }

        $invocation->parent_invocation_id ??= $prompt->parentInvocationId;
        $invocation->parent_tool_invocation_id ??= $prompt->parentToolInvocationId;
    }

    public function ensureFromPrompt(AgentPrompt $prompt): AiInvocation
    {
        $invocation = $this->find($prompt->invocationId);

        if ($invocation === null) {
            $invocation = $this->new(
                $prompt->invocationId,
                $prompt->agent,
                $prompt->provider->name(),
                $prompt->model,
            );
            $invocation->save();
        }

        $this->fill($invocation, $prompt->agent, $prompt->provider->name(), $prompt->model);
        $this->capturePrompt($invocation, $prompt);

        if ($invocation->isDirty()) {
            $invocation->save();
        }

        return $invocation;
    }

    public function ensureFromFailedOver(AgentFailedOver $event): AiInvocation
    {
        $invocation = $this->find($event->invocationId);

        if ($invocation === null) {
            $invocation = $this->new(
                $event->invocationId,
                $event->agent,
                $event->provider->name(),
                $event->model,
            );
            $invocation->save();
        }

        $this->fill($invocation, $event->agent, $event->provider->name(), $event->model);

        return $invocation;
    }

    public function ensureForStartingStep(StartingStep $event): AiInvocation
    {
        return $this->ensureForStep(
            $event->invocationId,
            $event->agent,
            $event->provider->name(),
            $event->model,
        );
    }

    public function ensureForPendingStep(PendingStep $step): AiInvocation
    {
        $existing = $this->find($step->invocationId);

        if ($existing !== null) {
            $this->fill($existing, $step->options?->agent, $step->provider, $step->model);

            return $existing;
        }

        $agent = $step->options?->agent;

        if ($agent === null) {
            throw new LogicException('Cannot create an invocation: PendingStep has no agent.');
        }

        $invocation = $this->new($step->invocationId, $agent, $step->provider, $step->model);
        $invocation->save();

        return $invocation;
    }

    public function ensureForStep(
        string $invocationId,
        Agent $agent,
        string $provider,
        string $model,
    ): AiInvocation {
        $existing = $this->find($invocationId);

        if ($existing !== null) {
            $this->fill($existing, $agent, $provider, $model);

            return $existing;
        }

        $invocation = $this->new($invocationId, $agent, $provider, $model);
        $invocation->save();

        return $invocation;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function attachments(AgentPrompt $prompt): ?array
    {
        if ($this->config->captureAttachments === 'none') {
            return null;
        }

        $attachments = [];

        foreach ($prompt->attachments as $attachment) {
            $normalized = $this->attachments->normalize($attachment);
            $attachments[] = $this->redactor->redact($normalized->data);
        }

        return $attachments;
    }
}
