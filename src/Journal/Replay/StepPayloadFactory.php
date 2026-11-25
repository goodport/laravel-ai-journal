<?php

namespace Goodport\AiJournal\Journal\Replay;

use Goodport\AiJournal\Journal\Context\ContextReader;
use Goodport\AiJournal\Journal\Recording\MessageNormalizer;
use Goodport\AiJournal\Journal\Recording\NormalizedPayload;
use Goodport\AiJournal\Journal\Recording\PayloadNormalizer;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\StartingStep;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Laravel\Ai\Tools\ToolNameResolver;
use Throwable;

final class StepPayloadFactory
{
    public function __construct(
        private MessageNormalizer $messages,
        private PayloadNormalizer $normalizer,
        private ContextReader $context,
        private ReplayScope $scope,
    ) {}

    public function fromStartingStep(StartingStep $event): StepPayload
    {
        $agent = $event->agent;
        $messages = $this->messages->normalize($event->messages);
        $options = $this->options($event->options, $event->provider->name());

        return $this->build(
            agent: $agent::class,
            provider: $event->provider->name(),
            model: $event->model,
            instructions: (string) $agent->instructions(),
            schema: $agent instanceof HasStructuredOutput ? $agent->schema(new JsonSchemaTypeFactory) : null,
            tools: $agent instanceof HasTools ? $this->toolNames($agent->tools()) : [],
            messages: $messages,
            options: $options,
            isFinal: $event->isFinalStep,
        );
    }

    public function fromPendingStep(PendingStep $step): StepPayload
    {
        $agent = $step->options?->agent;

        return $this->build(
            agent: $agent !== null ? $agent::class : null,
            provider: $step->provider,
            model: $step->model,
            instructions: $step->instructions,
            schema: $step->schema,
            tools: $this->toolNames($step->tools),
            messages: $this->messages->normalize($step->messages),
            options: $this->options($step->options, $step->provider),
            isFinal: $step->isFinalStep,
        );
    }

    /**
     * @param  array<int, mixed>  $tools
     * @return array<int, string>
     */
    private function toolNames(iterable $tools): array
    {
        $names = [];

        foreach ($tools as $tool) {
            $names[] = $this->toolName($tool);
        }

        sort($names);

        return $names;
    }

    private function toolName(mixed $tool): string
    {
        if ($tool instanceof Agent) {
            return $tool instanceof CanActAsTool ? $tool->name() : class_basename($tool);
        }

        if ($tool instanceof Tool || $tool instanceof ProviderTool) {
            return ToolNameResolver::resolve($tool);
        }

        return is_object($tool) ? class_basename($tool) : (string) $tool;
    }

    private function options(?TextGenerationOptions $options, string $provider): NormalizedPayload
    {
        if ($options === null) {
            return new NormalizedPayload([], true);
        }

        try {
            $data = [
                'max_steps' => $options->maxSteps,
                'max_tokens' => $options->maxTokens,
                'temperature' => $options->temperature,
                'top_p' => $options->topP,
                'tool_choice' => $options->toolChoice === null ? null : [
                    'mode' => $options->toolChoice->mode,
                    'tool_name' => $options->toolChoice->toolName,
                ],
                'cache_instructions' => $this->normalizer->normalize($options->cacheInstructions),
                'cache_tool_definitions' => $this->normalizer->normalize($options->cacheToolDefinitions),
                'provider_options' => $this->normalizer->normalize($options->providerOptions($provider)),
            ];

            return new NormalizedPayload($data, ! $this->normalizer->containsUnstable($data));
        } catch (Throwable) {
            return new NormalizedPayload([], false);
        }
    }

    /**
     * @param  array<int, string>  $tools
     * @param  array<string, mixed>|null  $schema
     */
    private function build(
        ?string $agent,
        string $provider,
        string $model,
        ?string $instructions,
        ?array $schema,
        array $tools,
        NormalizedPayload $messages,
        NormalizedPayload $options,
        bool $isFinal,
    ): StepPayload {
        $normalizedSchema = $this->normalizer->normalize($schema);
        $context = $this->context->fingerprintContext();
        $scope = $this->scope->current();

        $request = [
            'provider' => $provider,
            'model' => $model,
            'instructions' => $instructions,
            'messages' => $messages->data,
            'tools' => $tools,
            'schema' => $normalizedSchema,
            'options' => $options->data,
            'is_final_step' => $isFinal,
        ];

        return new StepPayload(
            request: $request,
            fingerprintData: [
                ...$request,
                'agent' => $agent,
                'context' => $context,
                'scope' => $scope,
            ],
            stable: $messages->stable
                && $options->stable
                && ! $this->normalizer->containsUnstable($normalizedSchema)
                && ! $this->normalizer->containsUnstable($context),
        );
    }
}
