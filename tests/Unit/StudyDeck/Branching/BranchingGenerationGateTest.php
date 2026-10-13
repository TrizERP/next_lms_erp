<?php

namespace Tests\Unit\StudyDeck\Branching;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\Contracts\Completer;
use PHPUnit\Framework\TestCase;
use Tests\Unit\H5P\Branching\BootsMiniApp;

/**
 * Generation goes through the same gate as every other Claude-written content
 * type: a chapter not listed in config('claude.chapter_ids') is not served by
 * Claude at all, and the refusal happens BEFORE anything is read or any model is
 * asked -- which is what lets this run with no database and no key.
 */
class BranchingGenerationGateTest extends TestCase
{
    use BootsMiniApp;

    protected function setUp(): void
    {
        $this->bootMiniApp();
    }

    protected function tearDown(): void
    {
        $this->shutDownMiniApp();
    }

    private function completerThatMustNotBeAsked(): Completer
    {
        return new class implements Completer {
            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                throw new \LogicException('the model was asked for a chapter the gate should have refused');
            }
        };
    }

    private function input(int $chapterId): array
    {
        return ['chapter' => (object) ['id' => $chapterId], 'sub_institute_id' => 1];
    }

    public function test_a_chapter_not_listed_is_refused_before_anything_is_read_or_asked(): void
    {
        config(['claude.chapter_ids' => '']);

        $result = (new ContentGenerationService())->generateBranchingScenario($this->input(8592), $this->completerThatMustNotBeAsked());

        $this->assertSame(422, $result['http']);
        $this->assertFalse($result['body']['success']);
        $this->assertStringContainsString('not enabled for this chapter', $result['body']['message']);
    }

    public function test_listing_one_chapter_does_not_enable_another(): void
    {
        config(['claude.chapter_ids' => '8592']);

        $result = (new ContentGenerationService())->generateBranchingScenario($this->input(7), $this->completerThatMustNotBeAsked());

        $this->assertSame(422, $result['http']);
    }
}
