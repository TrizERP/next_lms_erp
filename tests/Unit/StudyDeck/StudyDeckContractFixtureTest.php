<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\InteractionPlanner;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlideContentGenerator;
use App\Services\StudyDeck\SlideHtmlRenderer;
use App\Services\StudyDeck\SlidePlanner;
use App\Services\StudyDeck\StudyDeckService;
use Tests\TestCase;

/**
 * The deck this backend writes is the deck the student player reads. The player lives in
 * another repository (lms_k12 lib/study-deck), so the contract between them is a golden
 * file: this test pins what the pipeline emits, and the player's tests load the very same
 * file and play every activity in it through the real H5P runtime.
 *
 * To change the contract on purpose:
 *   UPDATE_GOLDEN=1 vendor/bin/phpunit --filter StudyDeckContractFixture
 * then copy tests/Fixtures/study-deck-golden.json to
 * lms_k12/lib/study-deck/fixtures/study-deck-golden.json.
 */
class StudyDeckContractFixtureTest extends TestCase
{
    use StudyDeckFixture;

    private const GOLDEN = __DIR__ . '/../../Fixtures/study-deck-golden.json';

    private function build(): array
    {
        $c = $this->completer([json_encode($this->planWithRoom()), json_encode($this->slideText()), json_encode($this->interactionReply())]);
        $images = new ImagePlanner($this->imageSearch($this->goodImage()), $this->memoryStore(), fn () => $this->png());
        $service = new StudyDeckService(
            new ConceptContextBuilder(), new QuestionSelector(), new LearningPlanBuilder(),
            new SlidePlanner($c), new SlideContentGenerator($c, 8), $images,
            new SlideHtmlRenderer(), new DeckValidator(images: $images), interactionPlanner: new InteractionPlanner($c),
        );

        return $service->fromRaw($this->raw(), ['min_slides' => 6, 'max_slides' => 8]);
    }

    public function test_the_emitted_deck_matches_the_golden_file_the_player_is_tested_against(): void
    {
        $result = $this->build();
        $this->assertTrue($result['report']['ok'], implode("\n", $result['report']['errors']));

        $json = json_encode($result['deck'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        if (getenv('UPDATE_GOLDEN') === '1') {
            file_put_contents(self::GOLDEN, $json);
        }

        $this->assertFileExists(self::GOLDEN, 'Run with UPDATE_GOLDEN=1 to create it.');
        $this->assertSame(
            str_replace("\r\n", "\n", (string) file_get_contents(self::GOLDEN)),
            $json,
            'The deck contract changed. If that is intended, regenerate the golden file and copy it to the player repo.'
        );
    }

    public function test_the_golden_deck_carries_all_three_interactions_for_the_player(): void
    {
        $deck = json_decode((string) file_get_contents(self::GOLDEN), true);

        $kinds = array_values(array_filter(array_map(fn ($s) => $s['interaction']['kind'] ?? null, $deck['slides'])));
        $this->assertSame(['compare', 'hotspots', 'reveal', 'scenario', 'steps', 'match'], $kinds);
        $this->assertSame(3, $deck['version']);
    }

    public function test_the_golden_deck_asks_for_no_branching_player_and_no_h5p_rows(): void
    {
        $deck = json_decode((string) file_get_contents(self::GOLDEN), true);

        foreach ($deck['slides'] as $slide) {
            foreach ($slide['activities'] as $a) {
                $this->assertNotSame('branching', $a['as']);
                $this->assertArrayNotHasKey('h5p_content_id', $a);
                $this->assertArrayNotHasKey('h5p_content_type', $a);
            }
        }
    }
}
