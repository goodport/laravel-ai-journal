<?php

namespace Goodport\AiJournal\Journal\Recording;

use Goodport\AiJournal\Journal\Config\JournalConfig;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;

final class MessageNormalizer
{
    public function __construct(
        private JournalConfig $config,
        private AttachmentNormalizer $attachments,
        private PayloadNormalizer $normalizer,
    ) {}

    /**
     * @param  array<int, mixed>  $messages
     */
    public function normalize(array $messages): NormalizedPayload
    {
        $data = [];
        $stable = true;

        foreach ($messages as $message) {
            $normalized = $this->normalizeMessage($message);

            $data[] = $normalized->data;
            $stable = $stable && $normalized->stable;
        }

        return new NormalizedPayload($data, $stable);
    }

    private function normalizeMessage(mixed $message): NormalizedPayload
    {
        if ($message instanceof UserMessage) {
            return $this->normalizeUserMessage($message);
        }

        if ($message instanceof AssistantMessage) {
            return $this->normalizeAssistantMessage($message);
        }

        if ($message instanceof ToolResultMessage) {
            return $this->normalizeToolResultMessage($message);
        }

        if ($message instanceof Message) {
            return new NormalizedPayload([
                'role' => $message->role->value,
                'content' => $message->content,
            ], true);
        }

        return new NormalizedPayload([
            'value' => $this->normalizer->normalize($message),
        ], ! $this->normalizer->containsUnstable($message));
    }

    private function normalizeUserMessage(UserMessage $message): NormalizedPayload
    {
        if ($this->config->captureAttachments === 'none') {
            return new NormalizedPayload([
                'role' => $message->role->value,
                'content' => $message->content,
            ], $message->attachments->isEmpty());
        }

        $attachments = [];
        $stable = true;

        foreach ($message->attachments as $attachment) {
            $normalized = $this->attachments->normalize($attachment);

            $attachments[] = $normalized->data;
            $stable = $stable && $normalized->stable;
        }

        return new NormalizedPayload([
            'role' => $message->role->value,
            'content' => $message->content,
            'attachments' => $attachments,
        ], $stable);
    }

    private function normalizeAssistantMessage(AssistantMessage $message): NormalizedPayload
    {
        $toolCalls = $this->normalizer->normalizeArray($message->toolCalls->all());
        $replayBlocks = $this->normalizer->normalizeArray($message->replayBlocks);

        return new NormalizedPayload([
            'role' => $message->role->value,
            'content' => $message->content,
            'tool_calls' => $toolCalls,
            'replay_blocks' => $replayBlocks,
            'replay_blocks_provider' => $message->replayBlocksProvider,
        ], ! $this->normalizer->containsUnstable($message->toolCalls->all()) && ! $this->normalizer->containsUnstable($message->replayBlocks));
    }

    private function normalizeToolResultMessage(ToolResultMessage $message): NormalizedPayload
    {
        $toolResults = $this->normalizer->normalizeArray($message->toolResults->all());

        return new NormalizedPayload([
            'role' => $message->role->value,
            'content' => $message->content,
            'tool_results' => $toolResults,
        ], ! $this->normalizer->containsUnstable($message->toolResults->all()));
    }
}
