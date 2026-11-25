<?php

namespace Goodport\AiJournal\Journal\Listeners;

use Closure;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Journal\Recording\InvocationRecorder;
use Goodport\AiJournal\Journal\Recording\InvocationStepRecorder;
use Goodport\AiJournal\Journal\Recording\JournalFailureHandler;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\StepFailed;
use Laravel\Ai\Events\StreamingAgent;

final class JournalEventSubscriber
{
    public function __construct(
        private JournalConfig $config,
        private InvocationRecorder $invocations,
        private InvocationStepRecorder $steps,
        private JournalFailureHandler $failures,
    ) {}

    public function handlePromptingAgent(PromptingAgent $event): void
    {
        $this->run(fn () => $this->invocations->start($event));
    }

    public function handleStreamingAgent(StreamingAgent $event): void
    {
        $this->run(fn () => $this->invocations->start($event));
    }

    public function handleStartingStep(StartingStep $event): void
    {
        $this->run(fn () => $this->steps->start($event));
    }

    public function handleStepCompleted(StepCompleted $event): void
    {
        $this->run(fn () => $this->steps->completed($event));
    }

    public function handleStepFailed(StepFailed $event): void
    {
        $this->run(fn () => $this->steps->failed($event));
    }

    public function handleAgentPrompted(AgentPrompted $event): void
    {
        $this->run(fn () => $this->invocations->succeed($event));
    }

    public function handleAgentStreamed(AgentStreamed $event): void
    {
        $this->run(fn () => $this->invocations->succeed($event));
    }

    public function handleAgentFailed(AgentFailed $event): void
    {
        $this->run(fn () => $this->invocations->fail($event));
    }

    public function handleAgentFailedOver(AgentFailedOver $event): void
    {
        $this->run(fn () => $this->invocations->failover($event));
    }

    private function run(Closure $callback): void
    {
        if (! $this->config->enabled) {
            return;
        }

        $this->failures->handle($callback);
    }
}
