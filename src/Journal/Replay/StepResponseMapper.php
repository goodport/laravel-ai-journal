<?php

namespace Goodport\AiJournal\Journal\Replay;

use Goodport\AiJournal\Journal\Recording\PayloadNormalizer;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ProviderToolCall;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;

final class StepResponseMapper
{
    private const REPLAYABLE_FINISH_REASONS = [
        'stop',
        'length',
        'content_filter',
    ];

    public function __construct(private PayloadNormalizer $normalizer) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(StepResponse $response): array
    {
        $data = $response->toArray();
        $data['reasoning'] = $response->reasoning;
        $data['pending_approvals'] = $response->pendingApprovals;

        return $this->normalizer->normalizeArray($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function isReplaySafe(array $data): bool
    {
        if (! in_array($data['finish_reason'] ?? null, self::REPLAYABLE_FINISH_REASONS, true)) {
            return false;
        }

        if (($data['tool_calls'] ?? []) !== [] || ($data['provider_tool_calls'] ?? []) !== []) {
            return false;
        }

        if (($data['pending_approvals'] ?? []) !== []) {
            return false;
        }

        if (($data['meta']['citations'] ?? []) !== []) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fromArray(array $data): StepResponse
    {
        $toolCalls = [];

        foreach ((is_array($data['tool_calls'] ?? null) ? $data['tool_calls'] : []) as $toolCall) {
            if (is_array($toolCall)) {
                $toolCalls[] = ToolCall::fromArray($toolCall);
            }
        }

        $providerToolCalls = [];

        foreach ((is_array($data['provider_tool_calls'] ?? null) ? $data['provider_tool_calls'] : []) as $providerToolCall) {
            if (is_array($providerToolCall)) {
                $providerToolCalls[] = new ProviderToolCall(
                    id: (string) ($providerToolCall['id'] ?? ''),
                    type: (string) ($providerToolCall['type'] ?? ''),
                    data: is_array($providerToolCall['data'] ?? null) ? $providerToolCall['data'] : [],
                );
            }
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        return new StepResponse(
            text: (string) ($data['text'] ?? ''),
            toolCalls: $toolCalls,
            finishReason: FinishReason::tryFrom((string) ($data['finish_reason'] ?? '')) ?? FinishReason::Unknown,
            usage: TextUsage::fromArray(is_array($data['usage'] ?? null) ? $data['usage'] : []),
            meta: new Meta(
                provider: is_string($meta['provider'] ?? null) ? $meta['provider'] : null,
                model: is_string($meta['model'] ?? null) ? $meta['model'] : null,
            ),
            structured: is_array($data['structured'] ?? null) ? $data['structured'] : null,
            continuationToken: is_string($data['continuation_token'] ?? null) ? $data['continuation_token'] : null,
            replayBlocks: is_array($data['replay_blocks'] ?? null) ? $data['replay_blocks'] : [],
            reasoning: is_string($data['reasoning'] ?? null) ? $data['reasoning'] : '',
            providerToolCalls: $providerToolCalls,
        );
    }
}
