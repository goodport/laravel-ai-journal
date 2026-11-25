<?php

namespace Goodport\AiJournal\Journal\Recording;

use Closure;
use Goodport\AiJournal\Journal\Config\JournalConfig;
use Throwable;

final class JournalFailureHandler
{
    public function __construct(private JournalConfig $config) {}

    /**
     * Run a journal write without breaking the main scenario.
     */
    public function handle(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            report($exception);

            if ($this->config->throw) {
                throw $exception;
            }

            return null;
        }
    }
}
