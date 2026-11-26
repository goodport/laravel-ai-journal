<?php

namespace Goodport\AiJournal\Journal\Recording;

use Goodport\AiJournal\Enums\StepStatus;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Journal\Cost\CostCalculator;
use Goodport\AiJournal\Journal\Errors\ErrorMetadataExtractor;
use Goodport\AiJournal\Journal\Redaction\PayloadRedactor;
use Goodport\AiJournal\Journal\Replay\StepFingerprint;
use Goodport\AiJournal\Journal\Replay\StepPayload;
use Goodport\AiJournal\Journal\Replay\StepPayloadFactory;
use Goodport\AiJournal\Journal\Replay\StepResponseMapper;
use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\StepFailed;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\PendingStep;

final class InvocationStepRecorder
{
    public function __construct(
        private JournalConfig $config,
        private InvocationRecorder $invocations,
        private AttemptRegistry $attempts,
        private StepPayloadFactory $payloads,
        private StepFingerprint $fingerprints,
        private StepResponseMapper $mapper,
        private PayloadNormalizer $normalizer,
        private PayloadRedactor $redactor,
        private CostCalculator $costs,
        private ErrorMetadataExtractor $errors,
    ) {}

    public function start(StartingStep $event): void
    {
        $invocation = $this->invocations->ensureForStartingStep($event);
        $attempt = $this->invocations->attemptFor($invocation);
        $payload = $this->payloads->fromStartingStep($event);
        $fingerprint = $this->fingerprints->fromPayload($payload);
        $step = $this->find($invocation, $attempt, $event->stepNumber) ?? new AiInvocationStep;

        $step->ai_invocation_id = $invocation->id;
        $step->attempt_number = $attempt;
        $step->step_number = $event->stepNumber;
        $step->status = StepStatus::Pending;
        $step->provider = $event->provider->name();
        $step->model = $event->model;
        $step->request = $this->config->captureStepRequest
            ? $this->redactor->redact($payload->request)
            : null;
        $step->request_fingerprint = $fingerprint;
        $step->response = null;
        $step->usage = null;
        $step->cost = null;
        $step->currency = $this->costs->currency();
        $step->duration_ms = null;
        $step->error_class = null;
        $step->error_message = null;
        $step->error_code = null;
        $step->http_status = null;
        $step->provider_request_id = null;
        $step->retry_after = null;
        $step->error_details = null;
        $step->replay_of_step_id = null;
        $step->save();
    }

    public function completed(StepCompleted $event): void
    {
        $invocation = $this->invocations->ensureForStep(
            $event->invocationId,
            $event->agent,
            $event->provider->name(),
            $event->model,
        );
        $attempt = $this->invocations->attemptFor($invocation);
        $step = $this->find($invocation, $attempt, $event->stepNumber) ?? new AiInvocationStep;
        $usage = $event->response->usage->toArray();

        $step->ai_invocation_id = $invocation->id;
        $step->attempt_number = $attempt;
        $step->step_number = $event->stepNumber;
        $step->status = StepStatus::Succeeded;
        $step->provider = $event->provider->name();
        $step->model = $event->model;
        $step->response = $this->config->captureResponse
            ? $this->redactor->redact($this->mapper->toArray($event->response))
            : null;
        $step->usage = $this->redactor->redact($this->normalizer->normalizeArray($usage));
        $step->cost = $this->costs->calculate($usage, $event->provider->name(), $event->model);
        $step->currency = $this->costs->currency();
        $step->duration_ms = (int) round($event->time);
        $step->error_class = null;
        $step->error_message = null;
        $step->error_code = null;
        $step->http_status = null;
        $step->provider_request_id = null;
        $step->retry_after = null;
        $step->error_details = null;
        $step->save();
    }

    public function failed(StepFailed $event): void
    {
        $invocation = $this->invocations->ensureForStep(
            $event->invocationId,
            $event->agent,
            $event->provider->name(),
            $event->model,
        );
        $attempt = $this->invocations->attemptFor($invocation);
        $step = $this->find($invocation, $attempt, $event->stepNumber) ?? new AiInvocationStep;

        $step->ai_invocation_id = $invocation->id;
        $step->attempt_number = $attempt;
        $step->step_number = $event->stepNumber;
        $step->status = StepStatus::Failed;
        $step->provider = $event->provider->name();
        $step->model = $event->model;
        $step->error_class = $event->exception::class;
        $step->error_message = $this->redactor->redactString($event->exception->getMessage());
        $error = $this->errors->extract($event->exception);
        $step->error_code = $error->errorCode;
        $step->http_status = $error->httpStatus;
        $step->provider_request_id = $error->providerRequestId;
        $step->retry_after = $error->retryAfter;
        $step->error_details = $error->details;
        $step->duration_ms = (int) round($event->time);
        $step->save();
    }

    public function replay(
        AiInvocation $invocation,
        PendingStep $pendingStep,
        AiInvocationStep $source,
        StepResponse $response,
        StepPayload $payload,
        int $attempt,
        ?string $fingerprint,
        ?int $durationMs,
    ): void {
        $step = $this->find($invocation, $attempt, $pendingStep->number) ?? new AiInvocationStep;
        $usage = $response->usage->toArray();

        $step->ai_invocation_id = $invocation->id;
        $step->attempt_number = $attempt;
        $step->step_number = $pendingStep->number;
        $step->status = StepStatus::Replayed;
        $step->provider = $pendingStep->provider;
        $step->model = $pendingStep->model;
        $step->request = $this->config->captureStepRequest
            ? $this->redactor->redact($payload->request)
            : null;
        $step->request_fingerprint = $fingerprint;
        $step->response = $this->redactor->redact($this->mapper->toArray($response));
        $step->usage = $this->redactor->redact($this->normalizer->normalizeArray($usage));
        $step->cost = 0;
        $step->currency = $this->costs->currency();
        $step->duration_ms = $durationMs;
        $step->error_class = null;
        $step->error_message = null;
        $step->error_code = null;
        $step->http_status = null;
        $step->provider_request_id = null;
        $step->retry_after = null;
        $step->error_details = null;
        $step->replay_of_step_id = $source->id;
        $step->save();

        $invocation->replayed = true;
        $invocation->save();
    }

    private function find(AiInvocation $invocation, int $attempt, int $stepNumber): ?AiInvocationStep
    {
        return AiInvocationStep::query()
            ->where('ai_invocation_id', $invocation->id)
            ->where('attempt_number', $attempt)
            ->where('step_number', $stepNumber)
            ->first();
    }
}
