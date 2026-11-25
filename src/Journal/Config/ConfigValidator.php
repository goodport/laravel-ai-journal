<?php

namespace Goodport\AiJournal\Journal\Config;

use Goodport\AiJournal\Journal\Redaction\Redactor;
use Goodport\AiJournal\Journal\Replay\ReplayScope;
use Illuminate\Contracts\Config\Repository;

final class ConfigValidator
{
    public function __construct(private Repository $config) {}

    /**
     * Read and validate the package configuration.
     */
    public function load(): JournalConfig
    {
        $redactor = $this->nullableString('redactor');

        if ($redactor !== null && (! class_exists($redactor) || ! is_subclass_of($redactor, Redactor::class))) {
            throw $this->invalid('redactor', 'null or a class-string implementing '.Redactor::class);
        }

        $scope = $this->string('replay.scope');

        if (! class_exists($scope) || ! is_subclass_of($scope, ReplayScope::class)) {
            throw $this->invalid('replay.scope', 'a class-string implementing '.ReplayScope::class);
        }

        $attachments = $this->string('capture.attachments');

        if (! in_array($attachments, ['none', 'metadata'], true)) {
            throw $this->invalid('capture.attachments', 'one of: none, metadata');
        }

        $fingerprintContext = $this->stringArray('replay.fingerprint_context');

        if (! array_is_list($fingerprintContext)) {
            throw $this->invalid('replay.fingerprint_context', 'a list of strings');
        }

        $ttl = $this->integer('replay.ttl');

        if ($ttl < 0) {
            throw $this->invalid('replay.ttl', 'an integer greater than or equal to 0');
        }

        $days = $this->integer('prune.days');

        if ($days <= 0) {
            throw $this->invalid('prune.days', 'an integer greater than 0');
        }

        $currency = $this->string('cost.currency');

        if (strlen($currency) !== 3) {
            throw $this->invalid('cost.currency', 'a 3-character string');
        }

        return new JournalConfig(
            enabled: $this->boolean('enabled'),
            connection: $this->nullableString('connection'),
            capturePrompt: $this->boolean('capture.prompt'),
            captureResponse: $this->boolean('capture.response'),
            captureStepRequest: $this->boolean('capture.step_request'),
            captureAttachments: $attachments,
            captureContext: $this->boolean('capture.context'),
            redactor: $redactor,
            traceKey: $this->string('context.trace'),
            scopeKey: $this->string('context.scope'),
            tagsKey: $this->string('context.tags'),
            replayEnabled: $this->boolean('replay.enabled'),
            replayTtl: $ttl,
            replayScope: $scope,
            fingerprintContext: $fingerprintContext,
            allowTools: $this->boolean('replay.allow_tools'),
            firstStepOnly: $this->boolean('replay.first_step_only'),
            currency: $currency,
            costProviders: $this->array('cost.providers'),
            pruneDays: $days,
            throw: $this->boolean('throw'),
        );
    }

    private function boolean(string $key): bool
    {
        $value = $this->config->get('ai-journal.'.$key);

        if (! is_bool($value)) {
            throw $this->invalid($key, 'bool');
        }

        return $value;
    }

    private function string(string $key): string
    {
        $value = $this->config->get('ai-journal.'.$key);

        if (! is_string($value) || $value === '') {
            throw $this->invalid($key, 'a non-empty string');
        }

        return $value;
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->config->get('ai-journal.'.$key);

        if ($value !== null && ! is_string($value)) {
            throw $this->invalid($key, 'null or a string');
        }

        return $value;
    }

    private function integer(string $key): int
    {
        $value = $this->config->get('ai-journal.'.$key);

        if (! is_int($value)) {
            throw $this->invalid($key, 'an integer');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function array(string $key): array
    {
        $value = $this->config->get('ai-journal.'.$key);

        if (! is_array($value)) {
            throw $this->invalid($key, 'an array');
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function stringArray(string $key): array
    {
        $value = $this->array($key);

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw $this->invalid($key, 'a list of strings');
            }
        }

        return array_values($value);
    }

    private function invalid(string $key, string $expected): InvalidConfigurationException
    {
        return new InvalidConfigurationException("Invalid ai-journal.{$key}: expected {$expected}.");
    }
}
