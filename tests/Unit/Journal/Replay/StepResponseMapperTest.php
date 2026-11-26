<?php

namespace Goodport\AiJournal\Tests\Unit\Journal\Replay;

use Goodport\AiJournal\Journal\Recording\PayloadNormalizer;
use Goodport\AiJournal\Journal\Recording\UploadedFileNormalizer;
use Goodport\AiJournal\Journal\Replay\StepResponseMapper;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\TestCase;

final class StepResponseMapperTest extends TestCase
{
    public function test_replay_safe_response_roundtrips(): void
    {
        $mapper = new StepResponseMapper(new PayloadNormalizer(new UploadedFileNormalizer));
        $response = $this->response();

        $data = $mapper->toArray($response);

        $this->assertTrue($mapper->isReplaySafe($data));

        $restored = $mapper->fromArray($data);

        $this->assertSame('OK', $restored->text);
        $this->assertSame(10, $restored->usage->inputTokens);
        $this->assertSame(FinishReason::Stop, $restored->finishReason);
    }

    public function test_tool_calls_and_approvals_are_not_replay_safe(): void
    {
        $mapper = new StepResponseMapper(new PayloadNormalizer(new UploadedFileNormalizer));

        $withTool = $this->response(toolCalls: [new ToolCall('tool-id', 'example_tool', [])]);

        $this->assertFalse($mapper->isReplaySafe($mapper->toArray($withTool)));

        $withApproval = $this->response(
            pendingApprovals: [new PendingApproval('approval-id', 'example_tool', [])],
        );

        $this->assertFalse($mapper->isReplaySafe($mapper->toArray($withApproval)));
    }

    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<int, PendingApproval>  $pendingApprovals
     */
    private function response(array $toolCalls = [], array $pendingApprovals = []): StepResponse
    {
        return new StepResponse(
            text: 'OK',
            toolCalls: $toolCalls,
            finishReason: FinishReason::Stop,
            usage: new TextUsage(10, 5),
            meta: new Meta('openai', 'gpt-test'),
            pendingApprovals: $pendingApprovals,
        );
    }
}
