<?php

namespace Goodport\AiJournal\Tests\Feature;

use Goodport\AiJournal\Facades\AiJournal;
use Goodport\AiJournal\Tests\TestCase;
use Laravel\Ai\AnonymousAgent;

final class ReadApiTest extends TestCase
{
    /**
     * The list API paginates and filters without loading heavy response fields.
     */
    public function test_invocation_list_is_filtered_and_lightweight(): void
    {
        $successId = '0190b4a0-0000-7000-8000-000000000061';
        $successPrompt = $this->dispatchPrompting($successId);
        $this->dispatchCompletedStep($successId, $successPrompt);
        $this->dispatchPrompted($successId, $successPrompt);

        $failedId = '0190b4a0-0000-7000-8000-000000000062';
        $failedPrompt = $this->dispatchPrompting($failedId);
        $this->dispatchFailedStep($failedId, $failedPrompt);
        $this->dispatchFailed($failedId, $failedPrompt);

        $page = AiJournal::invocations(['agent' => AnonymousAgent::class], 1);

        $this->assertSame(2, $page->total());
        $this->assertCount(1, $page->items());
        $this->assertArrayNotHasKey('prompt', $page->items()[0]->getAttributes());
        $this->assertArrayNotHasKey('response_text', $page->items()[0]->getAttributes());
        $this->assertArrayNotHasKey('structured', $page->items()[0]->getAttributes());

        $failed = AiJournal::invocations(['status' => 'failed']);

        $this->assertSame(1, $failed->total());
        $this->assertSame($failedId, $failed->items()[0]->invocation_id);
    }
}
