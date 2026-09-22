<?php

namespace Tests\Unit;

use App\Models\lms\h5p\H5pArithmeticQuiz;
use App\Models\lms\h5p\H5pCoursePresentation;
use App\Models\lms\h5p\H5pImageHotspotPoint;
use App\Models\lms\h5p\H5pImageHotspots;
use App\Models\lms\h5p\H5pMemoryGame;
use App\Models\lms\h5p\H5pMemoryGameCard;
use App\Models\lms\h5p\H5pPresentationSlide;
use App\Models\lms\h5p\H5pSlideElement;
use App\Services\lms\H5P\H5PArithmeticQuizBuilder;
use App\Services\lms\H5P\H5PCoursePresentationBuilder;
use App\Services\lms\H5P\H5PDragQuestionBuilder;
use App\Services\lms\H5P\H5PImageHotspotsBuilder;
use App\Services\lms\H5P\H5PMemoryGameBuilder;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * Covers the four builders added in the 2026-09-21 vertical.
 *
 * No database. Models are built in memory with their relations set, which is
 * all a builder reads -- so these run anywhere, including a CI box with no
 * MySQL, and they fail for the reason they name rather than on a fixture.
 *
 * What is tested is deliberately narrow: the places where a format mismatch
 * between this schema and H5P's could corrupt an activity silently. Those are
 * the bugs that reach a classroom, because the page still renders.
 */
class H5PContentTypeBuilderTest extends TestCase
{
    // =======================================================================
    // Image hotspots
    // =======================================================================

    private function hotspotItem(): H5pImageHotspots
    {
        $item = new H5pImageHotspots([
            'title' => 'Parts of a cell',
            'task_description' => 'Open each marker.',
            'background_image' => 'https://cdn.example.test/cell.png',
            'background_alt' => 'A plant cell in cross section.',
            'image_width' => 1200,
            'image_height' => 800,
            'default_icon' => 'plus',
            'default_icon_color' => '#4f46e5',
            'show_hotspot_numbers' => true,
            'points_per_hotspot' => 2,
            'pass_percentage' => 75,
            'enable_retry' => true,
            'single_popup_open' => true,
        ]);
        $item->id = 7;

        $text = new H5pImageHotspotPoint([
            'position_x' => 20.5, 'position_y' => 30.25,
            'header' => 'Nucleus', 'popup_type' => 'text',
            'body_text' => 'Holds the DNA & directs the cell.',
            'tooltip' => 'The control centre', 'popup_width' => 40,
        ]);
        $text->id = 91;
        $text->sort_order = 0;

        $image = new H5pImageHotspotPoint([
            'position_x' => 60, 'position_y' => 45,
            'header' => 'Chloroplast', 'popup_type' => 'image',
            'popup_image' => 'https://cdn.example.test/chloroplast.png',
            'popup_image_alt' => 'A chloroplast, magnified.',
            'icon_name' => 'info', 'icon_color' => '#047857',
            'popup_width' => 50,
        ]);
        $image->id = 92;
        $image->sort_order = 1;

        $rich = new H5pImageHotspotPoint([
            'position_x' => 75, 'position_y' => 70,
            // No header and no aria_label: this is the hotspot that has to
            // fall back to "Hotspot 3".
            'popup_type' => 'rich',
            'body_text' => '<p>The <strong>cell wall</strong> is rigid.</p>',
            'popup_width' => 40,
        ]);
        $rich->id = 93;
        $rich->sort_order = 2;

        $item->setRelation('points', new Collection([$text, $image, $rich]));

        return $item;
    }

    public function test_image_hotspots_popups_become_sub_content_lists(): void
    {
        $params = (new H5PImageHotspotsBuilder())->build($this->hotspotItem());

        $this->assertCount(3, $params['hotspots']);

        // A text popup is ONE AdvancedText entry, with the body escaped --
        // the ampersand is the tell. Unescaped, a body containing "<" would
        // silently become markup.
        $text = $params['hotspots'][0]['action'];
        $this->assertCount(1, $text);
        $this->assertSame('H5P.AdvancedText 1.1', $text[0]['library']);
        $this->assertStringContainsString('&amp;', $text[0]['params']['text']);

        // An image popup is ONE Image entry, not a text entry with a path in it.
        $image = $params['hotspots'][1]['action'];
        $this->assertCount(1, $image);
        $this->assertSame('H5P.Image 1.1', $image[0]['library']);
        $this->assertSame('https://cdn.example.test/chloroplast.png', $image[0]['params']['file']['path']);

        // A rich popup keeps its markup VERBATIM. Escaping it here would ship
        // "&lt;strong&gt;" to every reader.
        $rich = $params['hotspots'][2]['action'];
        $this->assertSame('<p>The <strong>cell wall</strong> is rigid.</p>', $rich[0]['params']['text']);
    }

    public function test_image_hotspot_without_a_header_still_has_an_accessible_name(): void
    {
        $params = (new H5PImageHotspotsBuilder())->build($this->hotspotItem());

        $this->assertSame('Nucleus', $params['hotspots'][0]['ariaLabel']);
        // Third hotspot, zero-based sort_order 2 -> "Hotspot 3".
        $this->assertSame('Hotspot 3', $params['hotspots'][2]['ariaLabel']);
    }

    public function test_image_hotspot_icons_resolve_against_the_item_defaults(): void
    {
        $params = (new H5PImageHotspotsBuilder())->build($this->hotspotItem());

        // Inherits both.
        $this->assertSame('plus', $params['hotspots'][0]['eduerpIcon']['name']);
        $this->assertSame('#4f46e5', $params['hotspots'][0]['eduerpIcon']['color']);

        // Overrides both.
        $this->assertSame('info', $params['hotspots'][1]['eduerpIcon']['name']);
        $this->assertSame('#047857', $params['hotspots'][1]['eduerpIcon']['color']);
    }

    public function test_image_hotspots_round_trip_through_parse(): void
    {
        $builder = new H5PImageHotspotsBuilder();
        $parsed = $builder->parse($builder->build($this->hotspotItem()));

        $this->assertSame('https://cdn.example.test/cell.png', $parsed['item']['background_image']);
        $this->assertSame(2, $parsed['item']['points_per_hotspot']);
        $this->assertSame(75, $parsed['item']['pass_percentage']);

        $this->assertCount(3, $parsed['points']);
        $this->assertSame(20.5, $parsed['points'][0]['position_x']);
        $this->assertSame('The control centre', $parsed['points'][0]['tooltip']);

        // An image popup survives as an image popup, with its alt text.
        $this->assertSame('image', $parsed['points'][1]['popup_type']);
        $this->assertSame('A chloroplast, magnified.', $parsed['points'][1]['popup_image_alt']);

        // A text popup comes back as `rich`, because what it round-tripped
        // through is HTML. See parseAction()'s note -- calling it `text` would
        // put markup into a plain-text editor.
        $this->assertSame('rich', $parsed['points'][0]['popup_type']);
    }

    // =======================================================================
    // Memory game
    // =======================================================================

    private function memoryGame(): H5pMemoryGame
    {
        $game = new H5pMemoryGame([
            'title' => 'States and capitals',
            'pairs_to_use' => 0,
            'active_pair_sets' => null,
            'allow_retry' => true,
            'use_grid' => true,
            'shuffle_cards' => true,
            'show_completion_screen' => true,
            'scoring_mode' => 'pairs',
            'points_per_pair' => 3,
            'pass_percentage' => 80,
            'track_time' => true,
            'time_limit_seconds' => 90,
            'theme_color' => '#4f46e5',
        ]);
        $game->id = 11;

        $mixed = new H5pMemoryGameCard([
            'pair_set' => 1,
            'front_type' => 'image', 'front_image' => 'https://cdn.example.test/kerala.png',
            'front_alt' => 'Outline of Kerala.',
            'back_type' => 'text', 'back_text' => 'Thiruvananthapuram',
            'match_description' => 'Thiruvananthapuram is the capital of Kerala.',
            'sort_order' => 0,
        ]);
        $mixed->id = 201;

        // Two copies of one picture: H5P's "find the identical pair", and the
        // case where matchImage must be ABSENT rather than duplicated.
        $identical = new H5pMemoryGameCard([
            'pair_set' => 2,
            'front_type' => 'image', 'front_image' => 'https://cdn.example.test/goa.png',
            'front_alt' => 'Outline of Goa.',
            'back_type' => 'image', 'back_image' => 'https://cdn.example.test/goa.png',
            'back_alt' => 'Outline of Goa.',
            'sort_order' => 1,
        ]);
        $identical->id = 202;

        $words = new H5pMemoryGameCard([
            'pair_set' => 2,
            'front_type' => 'text', 'front_text' => 'Assam',
            'back_type' => 'text', 'back_text' => 'Dispur',
            'sort_order' => 2,
        ]);
        $words->id = 203;

        $game->setRelation('cards', new Collection([$mixed, $identical, $words]));

        return $game;
    }

    public function test_memory_game_omits_match_image_for_an_identical_pair(): void
    {
        $params = (new H5PMemoryGameBuilder())->build($this->memoryGame());

        // Mixed pair: two different faces, so matchImage is present.
        $this->assertArrayHasKey('matchImage', $params['cards'][0]);

        // Identical pair: emitting matchImage would change the game from
        // "find the two identical pictures" into something else.
        $this->assertArrayNotHasKey('matchImage', $params['cards'][1]);
    }

    public function test_memory_game_carries_a_text_face_as_alt_text_and_verbatim(): void
    {
        $params = (new H5PMemoryGameBuilder())->build($this->memoryGame());

        // The word is what a foreign host's screen reader will announce.
        $this->assertSame('Thiruvananthapuram', $params['cards'][0]['matchAlt']);

        // And it is carried verbatim for a lossless round trip back into here.
        $this->assertSame('text', $params['cards'][0]['eduerpFaces']['back']['type']);
        $this->assertSame('Thiruvananthapuram', $params['cards'][0]['eduerpFaces']['back']['text']);
    }

    public function test_memory_game_counts_tiles_not_pairs_in_h5p_behaviour(): void
    {
        $params = (new H5PMemoryGameBuilder())->build($this->memoryGame());

        // Three authored PAIRS is six H5P cards. Passing 3 here would deal
        // half the deck in a stock H5P host.
        $this->assertSame(6, $params['behaviour']['numCardsToUse']);
    }

    public function test_memory_game_active_cards_respects_sets_and_limit(): void
    {
        $game = $this->memoryGame();

        $game->active_pair_sets = [2];
        $this->assertSame(2, $game->activeCards()->count());

        $game->pairs_to_use = 1;
        $this->assertSame(1, $game->activeCards()->count());

        // maxScore follows the same selection, not the authored total.
        $this->assertSame(3, $game->maxScore());
    }

    public function test_memory_game_round_trips_through_parse(): void
    {
        $builder = new H5PMemoryGameBuilder();
        $parsed = $builder->parse($builder->build($this->memoryGame()));

        $this->assertSame(3, $parsed['game']['points_per_pair']);
        $this->assertSame(80, $parsed['game']['pass_percentage']);
        $this->assertSame(90, $parsed['game']['time_limit_seconds']);

        $this->assertCount(3, $parsed['cards']);
        $this->assertSame('image', $parsed['cards'][0]['front_type']);
        $this->assertSame('text', $parsed['cards'][0]['back_type']);
        $this->assertSame('Thiruvananthapuram', $parsed['cards'][0]['back_text']);
        // The set a pair belonged to survives, so a re-import does not collapse
        // four sets into one.
        $this->assertSame(2, $parsed['cards'][1]['pair_set']);
    }

    // =======================================================================
    // Course presentation
    // =======================================================================

    private function deck(): H5pCoursePresentation
    {
        $deck = new H5pCoursePresentation([
            'title' => 'The water cycle',
            'theme' => 'default',
            'slide_transition' => 'fade',
            'show_progress_bar' => true,
            'show_keywords' => true,
            'show_summary_slide' => true,
            'enable_print' => false,
            'active_surface' => false,
            'enable_retry' => true,
            'enable_show_solution' => true,
            'pass_percentage' => 60,
        ]);
        $deck->id = 5;

        // Ids are deliberately not 0,1,2 and not in slide order, so anything
        // confusing an id for an ordinal is caught.
        $intro = new H5pPresentationSlide(['slide_index' => 0, 'title' => 'Intro']);
        $intro->id = 310;
        $check = new H5pPresentationSlide(['slide_index' => 1, 'title' => 'Check']);
        $check->id = 305;
        $end = new H5pPresentationSlide(['slide_index' => 2, 'title' => 'End']);
        $end->id = 322;

        $text = new H5pSlideElement([
            'element_type' => 'text', 'position_x' => 8, 'position_y' => 12,
            'width' => 84, 'height' => 40, 'content_text' => '<p>Water moves.</p>',
            'points' => 0, 'sort_order' => 0,
        ]);
        $text->id = 401;

        $mc = new H5pSlideElement([
            'element_type' => 'multiple_choice', 'position_x' => 8, 'position_y' => 14,
            'width' => 84, 'height' => 60, 'content_text' => 'Which stage makes vapour?',
            'options' => ['answers' => [
                ['text' => 'Evaporation', 'correct' => true],
                ['text' => 'Condensation', 'correct' => false],
            ]],
            'points' => 4, 'sort_order' => 0,
        ]);
        $mc->id = 402;

        // A go-to button aimed at the LAST slide, whose id (322) is bigger
        // than every other and whose ordinal is 2.
        $goto = new H5pSlideElement([
            'element_type' => 'goto_slide', 'position_x' => 70, 'position_y' => 85,
            'width' => 25, 'height' => 10, 'content_text' => 'Skip to the end',
            'options' => ['target_slide_id' => 322, 'label' => 'Skip to the end'],
            'points' => 0, 'sort_order' => 1,
        ]);
        $goto->id = 403;

        $intro->setRelation('elements', new Collection([$text]));
        $check->setRelation('elements', new Collection([$mc, $goto]));
        $end->setRelation('elements', new Collection());

        $deck->setRelation('slides', new Collection([$intro, $check, $end]));
        $deck->setRelation('elements', new Collection([$text, $mc, $goto]));

        return $deck;
    }

    private function presentationBuilder(): H5PCoursePresentationBuilder
    {
        return new H5PCoursePresentationBuilder(new H5PDragQuestionBuilder());
    }

    public function test_presentation_maps_each_element_to_its_library(): void
    {
        $params = $this->presentationBuilder()->build($this->deck());

        $slides = $params['presentation']['slides'];
        $this->assertCount(3, $slides);

        $this->assertSame('H5P.AdvancedText 1.1', $slides[0]['elements'][0]['action']['library']);
        $this->assertSame('H5P.MultiChoice 1.16', $slides[1]['elements'][0]['action']['library']);
        $this->assertSame('H5P.GoToSlide 1.3', $slides[1]['elements'][1]['action']['library']);
    }

    public function test_goto_slide_addresses_a_slide_by_ordinal_not_by_id(): void
    {
        $params = $this->presentationBuilder()->build($this->deck());

        $goto = $params['presentation']['slides'][1]['elements'][1]['action']['params'];

        // Slide id 322 is the third slide. H5P.GoToSlide counts from 1, so the
        // right answer is 3 -- and emitting the id would jump nowhere.
        $this->assertSame(3, $goto['goToSlide']);
    }

    public function test_multiple_choice_single_answer_is_derived_from_the_answer_key(): void
    {
        $deck = $this->deck();
        $params = $this->presentationBuilder()->build($deck);

        $behaviour = $params['presentation']['slides'][1]['elements'][0]['action']['params']['behaviour'];
        $this->assertTrue($behaviour['singleAnswer']);

        // Two correct answers must flip it, or the question is a radio group
        // that can never be answered correctly.
        $mc = $deck->slides[1]->elements[0];
        $mc->options = ['answers' => [
            ['text' => 'Evaporation', 'correct' => true],
            ['text' => 'Transpiration', 'correct' => true],
        ]];

        $params = $this->presentationBuilder()->build($deck);
        $behaviour = $params['presentation']['slides'][1]['elements'][0]['action']['params']['behaviour'];
        $this->assertFalse($behaviour['singleAnswer']);
    }

    public function test_presentation_max_score_counts_only_scored_elements(): void
    {
        // One multiple choice worth 4; the text and the button are worth 0.
        $this->assertSame(4, $this->deck()->maxScore());
    }

    public function test_presentation_round_trips_through_parse(): void
    {
        $builder = $this->presentationBuilder();
        $parsed = $builder->parse($builder->build($this->deck()));

        $this->assertSame(60, $parsed['deck']['pass_percentage']);
        $this->assertCount(3, $parsed['slides']);
        $this->assertSame('Intro', $parsed['slides'][0]['title']);

        $mc = $parsed['slides'][1]['elements'][0];
        $this->assertSame('multiple_choice', $mc['element_type']);
        $this->assertSame('Which stage makes vapour?', $mc['content_text']);
        $this->assertTrue($mc['options']['answers'][0]['correct']);

        // The button's target comes back as an INDEX, to be resolved to an id
        // by the controller once the slides exist.
        $goto = $parsed['slides'][1]['elements'][1];
        $this->assertSame('goto_slide', $goto['element_type']);
        $this->assertSame(2, $goto['options']['_target_slide_index']);
    }

    public function test_presentation_parse_skips_and_names_an_unknown_library(): void
    {
        $params = [
            'presentation' => ['slides' => [[
                'elements' => [[
                    'x' => 10, 'y' => 10, 'width' => 40, 'height' => 20,
                    'action' => ['library' => 'H5P.Crossword 0.4', 'params' => []],
                ]],
            ]]],
        ];

        $parsed = $this->presentationBuilder()->parse($params);

        $this->assertSame([], $parsed['slides'][0]['elements']);
        $this->assertStringContainsString('H5P.Crossword', $parsed['warnings'][0]);
    }

    // =======================================================================
    // Arithmetic quiz
    // =======================================================================

    private function quiz(): H5pArithmeticQuiz
    {
        $quiz = new H5pArithmeticQuiz([
            'title' => 'Times tables',
            'intro_text' => 'Twenty questions.',
            'show_intro' => true,
            'operations' => ['multiplication', 'division'],
            'difficulty_level' => 2,
            'max_questions' => 20,
            'enable_timer' => true,
            'time_limit_seconds' => 120,
            'points_per_question' => 2,
            'pass_percentage' => 60,
            'enable_retry' => true,
            'max_attempts' => 0,
        ]);
        $quiz->id = 3;

        return $quiz;
    }

    public function test_arithmetic_quiz_narrows_to_one_operation_for_h5p(): void
    {
        $params = (new H5PArithmeticQuizBuilder())->build($this->quiz());

        // What a stock H5P host will actually run.
        $this->assertSame('multiplication', $params['arithmeticType']);

        // And the full rule set, which is what this product reads back.
        $this->assertSame(['multiplication', 'division'], $params['eduerpGenerator']['operations']);
    }

    public function test_arithmetic_quiz_reports_the_narrowing_as_a_caveat(): void
    {
        $builder = new H5PArithmeticQuizBuilder();

        $caveat = $builder->exportCaveat($this->quiz());
        $this->assertNotNull($caveat);
        $this->assertStringContainsString('multiplication', $caveat);

        // A single-operation quiz loses nothing, so it must not warn.
        $single = $this->quiz();
        $single->operations = ['addition'];
        $this->assertNull($builder->exportCaveat($single));
    }

    public function test_arithmetic_quiz_exports_the_difficulty_range_it_generates_from(): void
    {
        $params = (new H5PArithmeticQuizBuilder())->build($this->quiz());

        // Level 2's range, from the model's one copy of it. A package that
        // named a different range from the generator's would produce a
        // different quiz on re-import.
        $this->assertSame(H5pArithmeticQuiz::DIFFICULTY_RANGES[2]['min'], $params['eduerpGenerator']['operandMin']);
        $this->assertSame(H5pArithmeticQuiz::DIFFICULTY_RANGES[2]['max'], $params['eduerpGenerator']['operandMax']);
    }

    public function test_arithmetic_quiz_round_trips_through_parse(): void
    {
        $builder = new H5PArithmeticQuizBuilder();
        $parsed = $builder->parse($builder->build($this->quiz()));

        $this->assertSame(['multiplication', 'division'], $parsed['quiz']['operations']);
        $this->assertSame(2, $parsed['quiz']['difficulty_level']);
        $this->assertSame(20, $parsed['quiz']['max_questions']);
        $this->assertSame(120, $parsed['quiz']['time_limit_seconds']);
        $this->assertSame(2, $parsed['quiz']['points_per_question']);
    }

    public function test_arithmetic_quiz_parses_a_stock_h5p_package(): void
    {
        // No eduerpGenerator: a package authored in the official editor.
        $parsed = (new H5PArithmeticQuizBuilder())->parse([
            'intro' => 'Practice.',
            'quizType' => 'arithmetic',
            'arithmeticType' => 'subtraction',
            'maxQuestions' => 15,
        ]);

        $this->assertSame(['subtraction'], $parsed['quiz']['operations']);
        $this->assertSame(15, $parsed['quiz']['max_questions']);
        // The easiest level, not a guessed hard one -- see parse()'s note.
        $this->assertSame(1, $parsed['quiz']['difficulty_level']);
    }

    public function test_arithmetic_quiz_max_score_multiplies_questions_by_points(): void
    {
        $this->assertSame(40, $this->quiz()->maxScore());
    }
}
