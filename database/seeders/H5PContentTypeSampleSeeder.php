<?php

namespace Database\Seeders;

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
use App\Services\lms\H5P\H5PImageHotspotsBuilder;
use App\Services\lms\H5P\H5PMemoryGameBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * One working activity of each 2026-09-21 type, for demos and for checking the
 * vertical end to end on a fresh environment.
 *
 *     php artisan db:seed --class=H5PContentTypeSampleSeeder
 *
 * Scoping comes from the environment so this can be pointed at whatever
 * chapter the person running it actually has:
 *
 *     H5P_SAMPLE_TENANT, H5P_SAMPLE_STANDARD, H5P_SAMPLE_SUBJECT, H5P_SAMPLE_CHAPTER
 *
 * Each sample is chosen to exercise the part of its type most likely to be
 * broken by a change and least likely to be noticed:
 *
 *   Image hotspots      one popup of each kind -- text, image, rich -- plus a
 *                       per-hotspot icon override and a tooltip.
 *   Memory game         a MIXED deck: picture fronts, word backs. That is the
 *                       case H5P.MemoryGame itself cannot express, so it is
 *                       the case the builder's export warning is about.
 *   Course presentation four slides carrying text, an image, a multiple
 *                       choice, a true/false and a branch, which is every
 *                       level of the three-table model at once.
 *   Arithmetic quiz     two operations at once, which is the case
 *                       H5P.ArithmeticQuiz cannot express.
 *
 * SAMPLES ARE PUBLISHED. They exist to be opened, and a draft is not visible
 * to a student surface -- a demo of the student experience against draft
 * content would show an empty list. They pass their own publish checks, which
 * is itself worth having: seeding is a smoke test of publishBlocker().
 *
 * MEDIA IS REFERENCED, NOT SHIPPED. The image paths below point at files this
 * seeder does not create. On an environment without them the activity is still
 * fully functional apart from the picture, and the alt text still reads --
 * which is deliberate, because it is also what a broken upload looks like and
 * is worth seeing once.
 *
 * Re-running replaces the samples rather than adding more, so a demo database
 * does not accumulate copies. Only rows this seeder created are touched --
 * they are matched on title within the target chapter.
 */
class H5PContentTypeSampleSeeder extends Seeder
{
    /** @var array<string,int> */
    private array $scope = [];

    public function run(): void
    {
        $this->scope = [
            'sub_institute_id' => (int) env('H5P_SAMPLE_TENANT', 1),
            'standard_id' => (int) env('H5P_SAMPLE_STANDARD', 1),
            'subject_id' => (int) env('H5P_SAMPLE_SUBJECT', 1),
            'chapter_id' => (int) env('H5P_SAMPLE_CHAPTER', 1),
        ];

        $seeded = 0;
        $seeded += $this->seedImageHotspots() ? 1 : 0;
        $seeded += $this->seedMemoryGame() ? 1 : 0;
        $seeded += $this->seedCoursePresentation() ? 1 : 0;
        $seeded += $this->seedArithmeticQuiz() ? 1 : 0;

        $this->command?->info(sprintf(
            'Seeded %d sample H5P %s into chapter %d.',
            $seeded,
            $seeded === 1 ? 'activity' : 'activities',
            $this->scope['chapter_id']
        ));
    }

    // -----------------------------------------------------------------------
    // Image hotspots
    // -----------------------------------------------------------------------

    private function seedImageHotspots(): bool
    {
        if (! $this->tableReady('h5p_image_hotspots')) {
            return false;
        }

        $title = 'Parts of the human digestive system';
        $this->purge(H5pImageHotspots::class, $title);

        $item = H5pImageHotspots::create($this->scope + [
            'title' => $title,
            'description' => 'An annotated diagram. Open each hotspot to read what that organ does.',
            'task_description' => 'Tap each marker to explore the digestive system.',
            'background_image' => asset('/h5p_content/samples/digestive-system.png'),
            'background_alt' => 'A labelled outline of the human digestive tract, from mouth to large intestine.',
            'image_width' => 1200,
            'image_height' => 800,
            'default_icon' => 'plus',
            'default_icon_color' => '#4f46e5',
            'show_hotspot_numbers' => true,
            'points_per_hotspot' => 1,
            'pass_percentage' => 100,
            'enable_retry' => true,
            'single_popup_open' => true,
            'feedback_bands' => [
                ['from' => 0, 'to' => 59, 'feedback' => 'Open the markers you have not read yet.'],
                ['from' => 60, 'to' => 99, 'feedback' => 'Nearly there — a few organs left to explore.'],
                ['from' => 100, 'to' => 100, 'feedback' => 'You explored every part of the diagram.'],
            ],
            'status' => 'published',
            'published_at' => now(),
            'library' => 'H5P.ImageHotspots 1.10',
        ]);

        // One popup of each kind, in the order an author is most likely to
        // build them.
        $points = [
            [
                'position_x' => 48.5,
                'position_y' => 18.0,
                'header' => 'Oesophagus',
                'popup_type' => 'text',
                'body_text' => 'A muscular tube about 25 cm long. Waves of muscle contraction — peristalsis — push food down to the stomach, which is why swallowing works upside down.',
                'tooltip' => 'The tube from throat to stomach',
                'aria_label' => 'Oesophagus hotspot',
            ],
            [
                'position_x' => 38.0,
                'position_y' => 34.5,
                'header' => 'Stomach',
                'popup_type' => 'rich',
                'body_text' => '<p>The stomach holds food for <strong>two to four hours</strong> and mixes it with gastric juice.</p><ul><li>Hydrochloric acid kills most bacteria</li><li>Pepsin begins protein digestion</li><li>Mucus stops the stomach digesting itself</li></ul>',
                'icon_name' => 'circle-alert',
                'icon_color' => '#b45309',
                'tooltip' => 'Where protein digestion starts',
                'aria_label' => 'Stomach hotspot',
                'popup_width' => 50,
            ],
            [
                'position_x' => 55.0,
                'position_y' => 45.0,
                'header' => 'Liver',
                'popup_type' => 'image',
                'popup_image' => asset('/h5p_content/samples/liver-detail.png'),
                'popup_image_alt' => 'Close-up of the liver showing the two main lobes and the gall bladder beneath.',
                'icon_name' => 'info',
                'tooltip' => 'Produces bile',
                'aria_label' => 'Liver hotspot',
            ],
            [
                'position_x' => 44.0,
                'position_y' => 62.0,
                'header' => 'Small intestine',
                'popup_type' => 'text',
                'body_text' => 'Around six metres long and folded into villi, giving it the surface area of a badminton court. Almost all nutrient absorption happens here.',
                'tooltip' => 'Where nutrients are absorbed',
                'aria_label' => 'Small intestine hotspot',
            ],
        ];

        foreach ($points as $order => $point) {
            H5pImageHotspotPoint::create($point + [
                'image_hotspots_id' => $item->id,
                'sort_order' => $order,
                'sub_institute_id' => $this->scope['sub_institute_id'],
            ]);
        }

        $this->cache($item, app(H5PImageHotspotsBuilder::class)->build($item->fresh('points')));

        return true;
    }

    // -----------------------------------------------------------------------
    // Memory game
    // -----------------------------------------------------------------------

    private function seedMemoryGame(): bool
    {
        if (! $this->tableReady('h5p_memory_game')) {
            return false;
        }

        $title = 'Match the state to its capital';
        $this->purge(H5pMemoryGame::class, $title);

        $game = H5pMemoryGame::create($this->scope + [
            'title' => $title,
            'description' => 'A mixed deck: each state outline is matched to the name of its capital.',
            'task_description' => 'Turn over two cards. Match each state to its capital city.',
            'pairs_to_use' => 0,
            'active_pair_sets' => null,
            'allow_retry' => true,
            'use_grid' => true,
            'shuffle_cards' => true,
            'show_completion_screen' => true,
            'completion_message' => 'Every state matched. Try it again against the clock.',
            'scoring_mode' => 'pairs',
            'points_per_pair' => 1,
            'pass_percentage' => 100,
            'track_time' => true,
            'time_limit_seconds' => 0,
            'theme_color' => '#4f46e5',
            'feedback_bands' => [
                ['from' => 0, 'to' => 99, 'feedback' => 'Some pairs are still unmatched.'],
                ['from' => 100, 'to' => 100, 'feedback' => 'All pairs matched.'],
            ],
            'status' => 'published',
            'published_at' => now(),
            'library' => 'H5P.MemoryGame 1.3',
        ]);

        // Picture front, word back -- the mixed deck H5P.MemoryGame has no
        // card template for. Exported, it warns; re-imported here, it is exact.
        $pairs = [
            ['Maharashtra', 'Mumbai', 'An outline map of Maharashtra.'],
            ['Kerala', 'Thiruvananthapuram', 'An outline map of Kerala.'],
            ['Gujarat', 'Gandhinagar', 'An outline map of Gujarat.'],
            ['Assam', 'Dispur', 'An outline map of Assam.'],
            ['Rajasthan', 'Jaipur', 'An outline map of Rajasthan.'],
            ['Odisha', 'Bhubaneswar', 'An outline map of Odisha.'],
        ];

        foreach ($pairs as $order => [$state, $capital, $alt]) {
            H5pMemoryGameCard::create([
                'memory_game_id' => $game->id,
                'pair_set' => 1,
                'front_type' => 'image',
                'front_image' => asset('/h5p_content/samples/state-' . strtolower($state) . '.png'),
                'front_alt' => $alt,
                'back_type' => 'text',
                'back_text' => $capital,
                'back_alt' => $capital,
                'match_description' => $capital . ' is the capital of ' . $state . '.',
                'sort_order' => $order,
                'sub_institute_id' => $this->scope['sub_institute_id'],
            ]);
        }

        $this->cache($game, app(H5PMemoryGameBuilder::class)->build($game->fresh('cards')));

        return true;
    }

    // -----------------------------------------------------------------------
    // Course presentation
    // -----------------------------------------------------------------------

    private function seedCoursePresentation(): bool
    {
        if (! $this->tableReady('h5p_course_presentation')) {
            return false;
        }

        $title = 'The water cycle';
        $this->purge(H5pCoursePresentation::class, $title);

        $deck = H5pCoursePresentation::create($this->scope + [
            'title' => $title,
            'description' => 'Four slides: what the cycle is, a diagram, and two checks for understanding.',
            'theme' => 'default',
            'slide_transition' => 'fade',
            'show_progress_bar' => true,
            'show_keywords' => true,
            'show_summary_slide' => true,
            'enable_print' => false,
            // Navigation stays visible: with it hidden, every slide would need
            // its own way forward, and this deck relies on the arrows.
            'active_surface' => false,
            'enable_retry' => true,
            'enable_show_solution' => true,
            'pass_percentage' => 60,
            'feedback_bands' => [
                ['from' => 0, 'to' => 59, 'feedback' => 'Read the first two slides again, then retry.'],
                ['from' => 60, 'to' => 100, 'feedback' => 'You have the cycle.'],
            ],
            'status' => 'published',
            'published_at' => now(),
            'library' => 'H5P.CoursePresentation 1.25',
        ]);

        $tenant = ['sub_institute_id' => $this->scope['sub_institute_id']];

        $slides = [];
        foreach ([
            'What the water cycle is',
            'The cycle, drawn',
            'Check: evaporation',
            'Check: true or false',
        ] as $index => $slideTitle) {
            $slides[$index] = H5pPresentationSlide::create([
                'presentation_id' => $deck->id,
                'slide_index' => $index,
                'title' => $slideTitle,
                'notes' => $index === 0
                    ? 'Ask the class where they have seen condensation before starting.'
                    : null,
            ] + $tenant);
        }

        $element = function (int $slideIndex, array $attributes) use ($deck, $slides, $tenant) {
            return H5pSlideElement::create($attributes + [
                'presentation_id' => $deck->id,
                'slide_id' => $slides[$slideIndex]->id,
            ] + $tenant);
        };

        // Slide 1 — text.
        $element(0, [
            'element_type' => 'text',
            'position_x' => 8, 'position_y' => 12, 'width' => 84, 'height' => 40,
            'content_text' => '<h2>The water cycle</h2><p>Water moves between the sea, the sky and the land without ever being used up. The same water has been going round for billions of years.</p><p>Four stages do the work: <strong>evaporation</strong>, <strong>condensation</strong>, <strong>precipitation</strong> and <strong>collection</strong>.</p>',
            'points' => 0, 'sort_order' => 0,
        ]);

        // Slide 2 — image.
        $element(1, [
            'element_type' => 'image',
            'position_x' => 10, 'position_y' => 10, 'width' => 80, 'height' => 70,
            'media_path' => asset('/h5p_content/samples/water-cycle.png'),
            'media_alt' => 'A diagram of the water cycle: the sun heats the sea, vapour rises and cools into cloud, rain falls on hills and runs back to the sea.',
            'points' => 0, 'sort_order' => 0,
        ]);

        // Slide 3 — multiple choice.
        $element(2, [
            'element_type' => 'multiple_choice',
            'position_x' => 8, 'position_y' => 14, 'width' => 84, 'height' => 60,
            'content_text' => 'Which stage turns liquid water into water vapour?',
            'options' => [
                'answers' => [
                    ['text' => 'Evaporation', 'correct' => true, 'feedback' => 'Yes — heat from the sun does it.'],
                    ['text' => 'Condensation', 'correct' => false, 'feedback' => 'That is vapour turning back into liquid.'],
                    ['text' => 'Precipitation', 'correct' => false, 'feedback' => 'That is water falling as rain, snow or hail.'],
                    ['text' => 'Collection', 'correct' => false, 'feedback' => 'That is water gathering in rivers, lakes and the sea.'],
                ],
                'randomise_answers' => true,
                'enable_retry' => true,
                'enable_solution' => true,
            ],
            'points' => 1, 'sort_order' => 0,
        ]);

        // Slide 4 — true/false.
        $element(3, [
            'element_type' => 'true_false',
            'position_x' => 8, 'position_y' => 16, 'width' => 84, 'height' => 40,
            'content_text' => 'The Earth gains new water each year from rainfall.',
            'options' => ['correct' => false, 'enable_retry' => true, 'enable_solution' => true],
            'points' => 1, 'sort_order' => 0,
        ]);

        // A branch: the diagram slide sends a learner straight to the first
        // check, which is what makes this sample exercise pass 3 of the save.
        $slides[1]->update(['next_slide_id' => $slides[2]->id]);

        $this->cache($deck, app(H5PCoursePresentationBuilder::class)->build($deck->fresh('slides.elements')));

        return true;
    }

    // -----------------------------------------------------------------------
    // Arithmetic quiz
    // -----------------------------------------------------------------------

    private function seedArithmeticQuiz(): bool
    {
        if (! $this->tableReady('h5p_arithmetic_quiz')) {
            return false;
        }

        $title = 'Times tables and division — two minute drill';
        $this->purge(H5pArithmeticQuiz::class, $title);

        $quiz = H5pArithmeticQuiz::create($this->scope + [
            'title' => $title,
            'description' => 'Twenty generated questions across multiplication and division.',
            'intro_text' => 'Twenty questions, two minutes. Answer as many as you can — you can retry as often as you like.',
            'show_intro' => true,
            // Two operations at once: the case H5P.ArithmeticQuiz cannot
            // express, and therefore the one the export caveat is about.
            'operations' => ['multiplication', 'division'],
            'difficulty_level' => 2,
            'max_questions' => 20,
            'enable_timer' => true,
            'time_limit_seconds' => 120,
            'points_per_question' => 1,
            'pass_percentage' => 60,
            'enable_retry' => true,
            'max_attempts' => 0,
            'feedback_bands' => [
                ['from' => 0, 'to' => 39, 'feedback' => 'Go back over the tables you found slow, then try again.'],
                ['from' => 40, 'to' => 59, 'feedback' => 'Getting there. Retry and beat your time.'],
                ['from' => 60, 'to' => 89, 'feedback' => 'Solid. Try the harder level next.'],
                ['from' => 90, 'to' => 100, 'feedback' => 'Fluent.'],
            ],
            'status' => 'published',
            'published_at' => now(),
            'library' => 'H5P.ArithmeticQuiz 1.1',
        ]);

        $this->cache($quiz, app(H5PArithmeticQuizBuilder::class)->build($quiz));

        return true;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function tableReady(string $table): bool
    {
        if (Schema::hasTable($table)) {
            return true;
        }

        $this->command?->warn($table . ' does not exist yet. Run the migrations first.');

        return false;
    }

    /**
     * Remove a previous run's copy of one sample.
     *
     * Force-deleted rather than soft-deleted: a soft-deleted sample would
     * still collide with the copy-title counter and would accumulate on every
     * re-seed, which is the thing this exists to prevent. Only rows matching
     * this seeder's own title in the target chapter are touched.
     *
     * @param  class-string<Model>  $model
     */
    private function purge(string $model, string $title): void
    {
        $model::withTrashed()
            ->where('sub_institute_id', $this->scope['sub_institute_id'])
            ->where('chapter_id', $this->scope['chapter_id'])
            ->where('title', $title)
            ->get()
            ->each(fn (Model $row) => $row->forceDelete());
    }

    /** @param array<string,mixed> $params */
    private function cache(Model $item, array $params): void
    {
        $item->forceFill([
            'content_json' => json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ])->saveQuietly();
    }
}
