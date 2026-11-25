<?php

use Goodport\AiJournal\Journal\Replay\ContextReplayScope;

return [
    'enabled' => env('AI_JOURNAL_ENABLED', true),

    'connection' => env('AI_JOURNAL_CONNECTION'),

    'capture' => [
        'prompt' => true,
        'response' => true,
        'step_request' => true,
        'attachments' => 'metadata',
        'context' => true,
    ],

    'redactor' => null,

    'context' => [
        'trace' => 'ai.trace_id',
        'scope' => 'ai.journal.scope',
        'tags' => 'ai.journal.tags',
    ],

    'replay' => [
        'enabled' => true,
        'ttl' => 0,
        'scope' => ContextReplayScope::class,
        'fingerprint_context' => [],
        'allow_tools' => false,
        'first_step_only' => true,
    ],

    'cost' => [
        'currency' => 'USD',
        'providers' => [],
    ],

    'prune' => [
        'days' => 30,
    ],

    'throw' => env('AI_JOURNAL_THROW', false),
];
