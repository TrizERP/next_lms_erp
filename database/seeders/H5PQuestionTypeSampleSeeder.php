<?php

namespace Database\Seeders;

use App\Models\lms\h5p\H5pSingleChoiceOption;
use App\Models\lms\h5p\H5pSingleChoiceQuestion;
use App\Models\lms\h5p\H5pSingleChoiceSet;
use App\Models\lms\h5p\H5pTrueFalse;
use App\Models\lms\h5p\H5pTrueFalseQuestion;
use App\Services\lms\H5P\H5PSingleChoiceSetBuilder;
use App\Services\lms\H5P\H5PTrueFalseBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * One working activity of each question type added on 2026-09-21, for demos
 * and for checking the vertical end to end on a fresh environment.
 *
 *     php artisan db:seed --class=H5PQuestionTypeSampleSeeder
 *
 * Scoping comes from the environment, so this can be pointed at whatever
 * chapter the person running it actually has:
 *
 *     H5P_SAMPLE_TENANT, H5P_SAMPLE_STANDARD, H5P_SAMPLE_SUBJECT, H5P_SAMPLE_CHAPTER
 *
 * The same variables H5PContentTypeSampleSeeder uses, so both can be run
 * against one chapter and the hub shows the whole estate together.
 *
 * WHAT EACH SAMPLE IS FOR. Both are written to the brief's spec -- five
 * science questions, ten general knowledge statements, instant feedback, retry
 * and show-solution on -- and both additionally exercise the thing about their
 * type most likely to break quietly:
 *
 *   Single choice set   the correct option is NOT first in author order in
 *                       every question. That is the case the builder's
 *                       flag-to-position conversion exists for, and a
 *                       regression in it would put the wrong answer at
 *                       `answers[0]` in every export while the activity
 *                       continued to look right in this app.
 *   True/false          ten statements, which is nine more than
 *                       H5P.TrueFalse can hold. That is the case the export
 *                       caveat is about, and the case the pool exists for.
 *
 * SAMPLES ARE PUBLISHED. They exist to be opened, and a draft is not visible
 * to a student surface -- a demo of the student experience against draft
 * content would show an empty list. They pass their own publish checks, which
 * is itself worth having: seeding is a smoke test of publishBlocker(). The
 * true/false sample in particular is a mix of true and false answers, because
 * a pool that is all one answer is refused by that check.
 *
 * Re-running replaces the samples rather than adding more, so a demo database
 * does not accumulate copies. Only rows this seeder created are touched --
 * they are matched on title within the target chapter.
 */
class H5PQuestionTypeSampleSeeder extends Seeder
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
        $seeded += $this->seedSingleChoiceSet() ? 1 : 0;
        $seeded += $this->seedTrueFalse() ? 1 : 0;

        $this->command?->info(sprintf(
            'Seeded %d sample H5P %s into chapter %d.',
            $seeded,
            $seeded === 1 ? 'activity' : 'activities',
            $this->scope['chapter_id']
        ));
    }

    // -----------------------------------------------------------------------
    // Single choice set -- five science questions
    // -----------------------------------------------------------------------

    private function seedSingleChoiceSet(): bool
    {
        if (! $this->tableReady('h5p_single_choice_set')) {
            return false;
        }

        $title = 'Science recap — states, forces and the solar system';
        $this->purge(H5pSingleChoiceSet::class, $title);

        $set = H5pSingleChoiceSet::create($this->scope + [
            'title' => $title,
            'description' => 'Five questions covering last week. Instant feedback, retry and solutions on.',
            'task_description' => 'Five quick questions. Pick one answer for each — you will see straight away whether it was right.',
            'auto_continue' => true,
            'timeout_correct_ms' => 2000,
            'timeout_wrong_ms' => 3000,
            'sound_effects' => false,
            'enable_retry' => true,
            'enable_show_solution' => true,
            // Questions stay in author order (they build on each other);
            // answers shuffle, so the position of the right one is not a
            // pattern a class learns after two attempts.
            'randomize_questions' => false,
            'randomize_answers' => true,
            'points_per_question' => 1,
            'pass_percentage' => 60,
            'show_progress' => true,
            'feedback_bands' => [
                ['from' => 0, 'to' => 39, 'feedback' => 'Read back over the chapter, then try again — you can retry as often as you like.'],
                ['from' => 40, 'to' => 59, 'feedback' => 'Close. Look at the ones you missed and have another go.'],
                ['from' => 60, 'to' => 99, 'feedback' => 'Good work. Check the solutions for the ones you missed.'],
                ['from' => 100, 'to' => 100, 'feedback' => 'Every one right. Well done.'],
            ],
            'status' => 'published',
            'published_at' => now(),
            'library' => 'H5P.SingleChoiceSet 1.11',
        ]);

        /*
         * The correct option is at a different position in each question -- 2nd,
         * 4th, 1st, 3rd, 2nd. See the class header: this is the case that
         * would break silently.
         */
        $questions = [
            [
                'question_text' => '<p>Which state of matter has a fixed volume but takes the shape of its container?</p>',
                'feedback_correct' => 'Yes — a liquid keeps its volume but flows to fit the container.',
                'feedback_incorrect' => 'Think about pouring water from a jug into a glass: what stayed the same?',
                'explanation' => 'A solid keeps both shape and volume. A liquid keeps volume only. A gas keeps neither.',
                'options' => [
                    ['option_text' => 'Solid', 'is_correct' => false, 'feedback' => 'A solid keeps its shape too — it does not flow to fit the container.'],
                    ['option_text' => 'Liquid', 'is_correct' => true, 'feedback' => null],
                    ['option_text' => 'Gas', 'is_correct' => false, 'feedback' => 'A gas spreads out to fill whatever it is in, so its volume is not fixed.'],
                    ['option_text' => 'Plasma', 'is_correct' => false, 'feedback' => 'Plasma behaves like a gas here — neither shape nor volume is fixed.'],
                ],
            ],
            [
                'question_text' => '<p>A book rests on a table and does not move. What can you say about the forces on it?</p>',
                'feedback_correct' => 'Right — balanced forces, so no change in motion.',
                'feedback_incorrect' => 'The book is still, not weightless. Something must be pushing up on it.',
                'explanation' => 'Gravity pulls the book down; the table pushes up with an equal force. Equal and opposite means balanced, and balanced forces leave motion unchanged.',
                'options' => [
                    ['option_text' => 'There are no forces on it', 'is_correct' => false, 'feedback' => 'Gravity is still pulling it down — it has not switched off.'],
                    ['option_text' => 'Gravity is the only force on it', 'is_correct' => false, 'feedback' => 'If that were true the book would fall through the table.'],
                    ['option_text' => 'The forces are unbalanced', 'is_correct' => false, 'feedback' => 'Unbalanced forces would make it start moving.'],
                    ['option_text' => 'The forces on it are balanced', 'is_correct' => true, 'feedback' => null],
                ],
            ],
            [
                'question_text' => '<p>Which planet is closest to the Sun?</p>',
                'feedback_correct' => 'Correct — Mercury is first out from the Sun.',
                'feedback_incorrect' => 'Venus is the hottest, but it is not the closest.',
                'explanation' => 'The order outward is Mercury, Venus, Earth, Mars. Venus is hotter than Mercury because of its atmosphere, which is why it is a common wrong answer.',
                'options' => [
                    ['option_text' => 'Mercury', 'is_correct' => true, 'feedback' => null],
                    ['option_text' => 'Venus', 'is_correct' => false, 'feedback' => 'Venus is the hottest planet, but Mercury is closer to the Sun.'],
                    ['option_text' => 'Earth', 'is_correct' => false, 'feedback' => 'Earth is third out from the Sun.'],
                    ['option_text' => 'Mars', 'is_correct' => false, 'feedback' => 'Mars is fourth out, further away than Earth.'],
                ],
            ],
            [
                'question_text' => '<p>What is the main gas plants take in from the air to make food?</p>',
                'feedback_correct' => 'Yes — carbon dioxide in, oxygen out.',
                'feedback_incorrect' => 'Plants release oxygen during photosynthesis. What do they take in?',
                'explanation' => 'Photosynthesis takes in carbon dioxide and water and, using light, makes glucose and oxygen.',
                'options' => [
                    ['option_text' => 'Oxygen', 'is_correct' => false, 'feedback' => 'Oxygen is what photosynthesis gives out, not what it takes in.'],
                    ['option_text' => 'Nitrogen', 'is_correct' => false, 'feedback' => 'Most of the air is nitrogen, but plants do not use it to make food.'],
                    ['option_text' => 'Carbon dioxide', 'is_correct' => true, 'feedback' => null],
                    ['option_text' => 'Hydrogen', 'is_correct' => false, 'feedback' => 'The hydrogen a plant uses comes from water, not from the air.'],
                ],
            ],
            [
                'question_text' => '<p>Water boils at 100&nbsp;&deg;C at sea level. What is this change called?</p>',
                'feedback_correct' => 'Correct — liquid to gas is evaporation, and boiling is evaporation throughout the liquid.',
                'feedback_incorrect' => 'Condensation is the other direction: gas turning back into liquid.',
                'explanation' => 'Melting is solid to liquid. Evaporation is liquid to gas. Condensation is gas to liquid. Freezing is liquid to solid.',
                'options' => [
                    ['option_text' => 'Melting', 'is_correct' => false, 'feedback' => 'Melting is solid turning into liquid — ice into water.'],
                    ['option_text' => 'Evaporation', 'is_correct' => true, 'feedback' => null],
                    ['option_text' => 'Condensation', 'is_correct' => false, 'feedback' => 'That is the opposite change — steam turning back into water.'],
                    ['option_text' => 'Freezing', 'is_correct' => false, 'feedback' => 'Freezing is liquid turning into solid.'],
                ],
            ],
        ];

        $audit = ['sub_institute_id' => $this->scope['sub_institute_id'], 'created_at' => now()];

        foreach ($questions as $order => $question) {
            $options = $question['options'];
            unset($question['options']);

            $row = H5pSingleChoiceQuestion::create($question + [
                'set_id' => $set->id,
                'sort_order' => $order,
            ] + $audit);

            foreach ($options as $optionOrder => $option) {
                H5pSingleChoiceOption::create($option + [
                    'question_id' => $row->id,
                    'set_id' => $set->id,
                    'sort_order' => $optionOrder,
                ] + $audit);
            }
        }

        $set->load('questions.options');
        $this->cache($set, app(H5PSingleChoiceSetBuilder::class)->build($set));

        return true;
    }

    // -----------------------------------------------------------------------
    // True/false -- ten general knowledge statements
    // -----------------------------------------------------------------------

    private function seedTrueFalse(): bool
    {
        if (! $this->tableReady('h5p_true_false')) {
            return false;
        }

        $title = 'General knowledge — true or false';
        $this->purge(H5pTrueFalse::class, $title);

        $item = H5pTrueFalse::create($this->scope + [
            'title' => $title,
            'description' => 'Ten statements. Instant feedback, retry and solutions on.',
            'task_description' => 'Decide whether each statement is true or false. You will find out straight away.',
            'enable_retry' => true,
            'enable_show_solution' => true,
            // Instant feedback: the answer is marked the moment it is chosen,
            // which is what the brief asks for and what makes this a recap
            // rather than an assessment. With it on, the Check button has
            // nothing left to do.
            'auto_check' => true,
            'enable_check_button' => false,
            'confirm_check_dialog' => false,
            'confirm_retry_dialog' => false,
            // The whole pool, in author order. Set `questions_to_ask` to 5 to
            // see the pool behaviour -- half the statements, different ones
            // each attempt.
            'randomize_questions' => false,
            'questions_to_ask' => 0,
            'points_per_question' => 1,
            'pass_percentage' => 60,
            'show_progress' => true,
            'feedback_bands' => [
                ['from' => 0, 'to' => 39, 'feedback' => 'Have another go — read each statement all the way through first.'],
                ['from' => 40, 'to' => 59, 'feedback' => 'Not far off. Check the solutions and retry.'],
                ['from' => 60, 'to' => 89, 'feedback' => 'Good. A few caught you out — the explanations say why.'],
                ['from' => 90, 'to' => 100, 'feedback' => 'Excellent.'],
            ],
            'status' => 'published',
            'published_at' => now(),
            'library' => 'H5P.TrueFalse 1.8',
        ]);

        /*
         * Five true, five false, alternating irregularly.
         *
         * Not a decorative choice: the publish check refuses a pool whose
         * answers are all the same, because it can be answered without being
         * read. An even split is the honest version of that rule.
         */
        $statements = [
            ['The Pacific is the largest ocean on Earth.', true,
                'Yes — it is bigger than all the land on Earth put together.',
                'It is the largest: about a third of the planet\'s surface.'],
            ['Lightning never strikes the same place twice.', false,
                'Right — it is a saying, not a fact.',
                'Tall structures are struck repeatedly. The Empire State Building is hit around twenty times a year.'],
            ['The Sun is a star.', true,
                'Correct — an ordinary one, just very close to us.',
                'It only looks different from the others because it is about 270,000 times nearer than the next one.'],
            ['Bats are blind.', false,
                'Right — every bat species can see.',
                'Bats can see, and many see well. They use echolocation in the dark as well as sight, not instead of it.'],
            ['Mount Everest is the highest mountain above sea level.', true,
                'Yes — 8,849 m above sea level.',
                'Measured from its base rather than from sea level, Mauna Kea is taller. Above sea level, Everest is highest.'],
            ['The Great Wall of China is visible from the Moon with the naked eye.', false,
                'Correct — it is not.',
                'It is long but only a few metres wide. No human-made structure is visible from the Moon unaided.'],
            ['Water conducts electricity better when it has salt dissolved in it.', true,
                'Yes — the dissolved ions carry the current.',
                'Pure water is a poor conductor. The ions from dissolved salt are what carry the charge.'],
            ['Humans only use ten per cent of their brains.', false,
                'Right — a myth.',
                'Brain imaging shows activity across virtually the whole brain over the course of a day.'],
            ['Antarctica is a desert.', true,
                'Yes — a desert is defined by rainfall, not by heat.',
                'A desert gets under 250 mm of precipitation a year. Most of Antarctica gets far less than that.'],
            ['Sound travels faster in air than in water.', false,
                'Correct — it is about four times faster in water.',
                'Sound moves faster through denser media: roughly 343 m/s in air and 1,480 m/s in water.'],
        ];

        $audit = ['sub_institute_id' => $this->scope['sub_institute_id'], 'created_at' => now()];

        foreach ($statements as $order => [$text, $correct, $feedbackCorrect, $explanation]) {
            H5pTrueFalseQuestion::create([
                'true_false_id' => $item->id,
                'question_text' => '<p>' . $text . '</p>',
                'correct_answer' => $correct,
                'feedback_correct' => $feedbackCorrect,
                'feedback_incorrect' => $correct
                    ? 'Not quite — this one is true.'
                    : 'Not quite — this one is false.',
                'explanation' => $explanation,
                'sort_order' => $order,
            ] + $audit);
        }

        $item->load('questions');
        $this->cache($item, app(H5PTrueFalseBuilder::class)->build($item));

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
     * re-seed, which is the thing this exists to prevent. Children go with it,
     * because a force-deleted parent leaves orphans the cascade would
     * otherwise never see.
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
            ->each(function (Model $row) {
                foreach (['options', 'questions'] as $relation) {
                    if (method_exists($row, $relation)) {
                        $row->{$relation}()->withTrashed()->get()->each(fn (Model $child) => $child->forceDelete());
                    }
                }

                $row->forceDelete();
            });
    }

    /** @param array<string,mixed> $params */
    private function cache(Model $item, array $params): void
    {
        $item->forceFill([
            'content_json' => json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ])->saveQuietly();
    }
}
