<?php

namespace Tests\Unit\StudyDeck;

use App\Domain\AI\Support\GeminiClient;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\Story\StoryPixelJudge;
use PHPUnit\Framework\TestCase;

/** What the vision judge asks for and returns: the openable parts of a picture, never for a generic one. A fake client answers; no network. */
class StoryPixelJudgeTest extends TestCase
{
    /** @return array{0:StoryPixelJudge,1:object} the judge and the fake client (which keeps the last prompt it was asked) */
    private function judge(string $reply): array
    {
        $client = new class($reply) extends GeminiClient {
            public string $lastPrompt = '';

            public function __construct(private string $reply)
            {
            }

            public function analyzeImage(string $prompt, string $imageBytes, string $mimeType, ?string $model = null, ?int $timeout = null): ?string
            {
                $this->lastPrompt = $prompt;

                return $this->reply;
            }
        };

        return [new StoryPixelJudge($client), $client];
    }

    private const REPLY = '{"matches": true, "shows": "Students timing a cyclist", "reason": "it fits", "focus": {"x": 10, "y": 10, "w": 30, "h": 30}, "label": "Stopwatch", "notice": "Look at the stopwatch.", "parts": [{"label": "Stopwatch", "x": 10, "y": 10, "w": 25, "h": 25, "text": "The stopwatch starts at the first mark."}, {"label": "Rider", "x": 55, "y": 40, "w": 30, "h": 40, "text": "The rider covers the marked distance."}, "junk"]}';

    public function test_the_exact_and_related_checks_ask_for_the_parts_and_return_them(): void
    {
        foreach (['Measuring speed', ImagePlanner::RELATED_PREFIX . 'Measuring speed'] as $purpose) {
            [$judge, $client] = $this->judge(self::REPLY);

            $r = $judge('bytes', 'image/png', 'stopwatch', $purpose, 'Speed');

            $this->assertTrue($r['ok']);
            $this->assertStringContainsString('"parts"', $client->lastPrompt);
            $this->assertStringContainsString('two to four separate parts', $client->lastPrompt);
            $this->assertCount(2, $r['parts'], 'only well-formed parts are passed on');
            $this->assertSame('Stopwatch', $r['parts'][0]['label']);
        }
    }

    public function test_a_generic_check_is_told_the_picture_is_not_about_the_scene_and_returns_no_parts_even_if_the_model_gives_some(): void
    {
        [$judge, $client] = $this->judge(self::REPLY);

        $r = $judge('bytes', 'image/png', 'laboratory', ImagePlanner::GENERIC_PREFIX . 'A clear educational picture', '');

        $this->assertStringContainsString('GENERIC educational picture', $client->lastPrompt);
        $this->assertStringContainsString('Give "parts": []', $client->lastPrompt);
        $this->assertArrayNotHasKey('parts', $r, 'a picture that is not about the scene offers no parts about it');
    }

    public function test_an_illustration_check_also_asks_for_parts(): void
    {
        [$judge, $client] = $this->judge(self::REPLY);

        $r = $judge('bytes', 'image/png', 'scene', StoryPixelJudge::AI_PREFIX . 'Priya opens her notebook', 'Models');

        $this->assertStringContainsString('ILLUSTRATION', $client->lastPrompt);
        $this->assertCount(2, $r['parts']);
    }

    public function test_a_reply_without_parts_is_still_a_valid_check(): void
    {
        [$judge] = $this->judge('{"matches": true, "shows": "A clear photo of a flame", "reason": "ok"}');

        $r = $judge('bytes', 'image/png', 'flame', 'A flame', '');

        $this->assertTrue($r['ok']);
        $this->assertArrayNotHasKey('parts', $r);
    }
}
