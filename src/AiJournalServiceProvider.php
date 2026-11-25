<?php

namespace Goodport\AiJournal;

use Goodport\AiJournal\Console\PruneJournalCommand;
use Goodport\AiJournal\Journal\Config\ConfigValidator;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Goodport\AiJournal\Journal\Listeners\JournalEventSubscriber;
use Goodport\AiJournal\Journal\Recording\AttemptRegistry;
use Goodport\AiJournal\Journal\Recording\JournalFailureHandler;
use Goodport\AiJournal\Journal\Redaction\NullRedactor;
use Goodport\AiJournal\Journal\Redaction\Redactor;
use Goodport\AiJournal\Journal\Replay\ReplayScope;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\StepFailed;
use Laravel\Ai\Events\StreamingAgent;

final class AiJournalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-journal.php', 'ai-journal');

        $this->app->singleton(JournalConfig::class, fn (Application $app) => $app->make(ConfigValidator::class)->load());
        $this->app->singleton(AttemptRegistry::class);
        $this->app->singleton(JournalFailureHandler::class);
        $this->app->singleton(AiJournalManager::class);

        $this->app->bind(Redactor::class, function (Application $app): Redactor {
            $config = $app->make(JournalConfig::class);

            return $config->redactor === null
                ? new NullRedactor
                : $app->make($config->redactor);
        });

        $this->app->bind(ReplayScope::class, function (Application $app): ReplayScope {
            return $app->make($app->make(JournalConfig::class)->replayScope);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-journal.php' => config_path('ai-journal.php'),
            ], 'ai-journal-config');

            $this->commands([
                PruneJournalCommand::class,
            ]);
        }

        if ($this->app['config']->get('ai-journal.enabled', true)) {
            Event::listen(PromptingAgent::class, [JournalEventSubscriber::class, 'handlePromptingAgent']);
            Event::listen(StreamingAgent::class, [JournalEventSubscriber::class, 'handleStreamingAgent']);
            Event::listen(StartingStep::class, [JournalEventSubscriber::class, 'handleStartingStep']);
            Event::listen(StepCompleted::class, [JournalEventSubscriber::class, 'handleStepCompleted']);
            Event::listen(StepFailed::class, [JournalEventSubscriber::class, 'handleStepFailed']);
            Event::listen(AgentPrompted::class, [JournalEventSubscriber::class, 'handleAgentPrompted']);
            Event::listen(AgentStreamed::class, [JournalEventSubscriber::class, 'handleAgentStreamed']);
            Event::listen(AgentFailed::class, [JournalEventSubscriber::class, 'handleAgentFailed']);
            Event::listen(AgentFailedOver::class, [JournalEventSubscriber::class, 'handleAgentFailedOver']);
        }
    }
}
