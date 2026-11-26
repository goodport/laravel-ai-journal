<?php

namespace Goodport\AiJournal\Journal\Recording;

use DateTimeInterface;
use Goodport\AiJournal\Enums\InvocationStatus;
use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Journal\Cost\CostCalculator;
use Goodport\AiJournal\Journal\Errors\ErrorMetadataExtractor;
use Goodport\AiJournal\Journal\Redaction\PayloadRedactor;
use Goodport\AiJournal\Models\AiInvocation;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class InvocationFinalizer
{
    public function __construct(
        private JournalConfig $config,
        private PayloadNormalizer $normalizer,
        private PayloadRedactor $redactor,
        private CostCalculator $costs,
        private AttemptTracker $attempts,
        private ErrorMetadataExtractor $errors,
    ) {}

    public function succeed(
        AiInvocation $invocation,
        AgentResponse $response,
        bool $streamed,
        int $attempt,
        DateTimeInterface $finishedAt,
    ): void {
        $invocation->status = $response->hasPendingApprovals()
            ? InvocationStatus::PendingApproval
            : InvocationStatus::Succeeded;
        $invocation->streamed = (bool) $invocation->streamed || $streamed;
        $invocation->finished_at = $finishedAt;
        $invocation->duration_ms = $this->duration($invocation->started_at, $finishedAt);
        $invocation->conversation_id = $response->conversationId;
        $invocation->error_class = null;
        $invocation->error_message = null;
        $invocation->error_code = null;
        $invocation->http_status = null;
        $invocation->provider_request_id = null;
        $invocation->retry_after = null;
        $invocation->error_details = null;

        if ($this->config->captureResponse) {
            $invocation->response_text = $this->redactor->redactString($response->text);
            $invocation->structured = $this->redactor->redact(
                $response instanceof StructuredAgentResponse ? $response->structured : null,
            );
        }

        $invocation->usage = $this->redactor->redact($this->normalizer->normalize($response->usage->toArray()));
        $invocation->meta = $this->redactor->redact($this->normalizer->normalize($response->meta->toArray()));

        if ($response->meta->provider !== null) {
            $invocation->provider = $response->meta->provider;
        }

        if ($response->meta->model !== null) {
            $invocation->model = $response->meta->model;
        }

        $steps = $invocation->steps()->get(['status', 'cost']);

        $invocation->cost = $this->costs->sum($steps);
        $invocation->currency = $this->costs->currency();
        $invocation->replayed = (bool) $invocation->replayed
            || $steps->contains(fn ($step): bool => $step->status === StepStatus::Replayed);

        $this->attempts->succeed(
            $invocation,
            $attempt,
            $invocation->provider,
            $invocation->model,
            $finishedAt,
        );
        $invocation->save();
        $this->attempts->forget($invocation);
    }

    public function fail(
        AiInvocation $invocation,
        Throwable $exception,
        int $attempt,
        DateTimeInterface $finishedAt,
    ): void {
        $invocation->status = InvocationStatus::Failed;
        $invocation->finished_at = $finishedAt;
        $invocation->duration_ms = $this->duration($invocation->started_at, $finishedAt);
        $invocation->error_class = $exception::class;
        $invocation->error_message = $this->redactor->redactString($exception->getMessage());
        $error = $this->errors->extract($exception);
        $invocation->error_code = $error->errorCode;
        $invocation->http_status = $error->httpStatus;
        $invocation->provider_request_id = $error->providerRequestId;
        $invocation->retry_after = $error->retryAfter;
        $invocation->error_details = $error->details;

        $this->attempts->fail(
            $invocation,
            $attempt,
            $exception::class,
            $this->redactor->redactString($exception->getMessage()),
            $error,
            $finishedAt,
        );
        $invocation->save();
        $this->attempts->forget($invocation);
    }

    private function duration(?DateTimeInterface $startedAt, DateTimeInterface $finishedAt): ?int
    {
        if ($startedAt === null) {
            return null;
        }

        $startedMilliseconds = (float) $startedAt->format('U.u') * 1000;
        $finishedMilliseconds = (float) $finishedAt->format('U.u') * 1000;

        return (int) round($finishedMilliseconds - $startedMilliseconds);
    }
}
