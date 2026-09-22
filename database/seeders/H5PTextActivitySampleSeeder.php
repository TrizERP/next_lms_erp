<?php

namespace Database\Seeders;

use App\Models\lms\h5p\H5pTextActivity;
use App\Models\lms\h5p\H5pTextActivityBlank;
use App\Services\lms\H5P\H5PTextActivityBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * One working sample of each text-passage type -- Drag the Words, Fill in the
 * Blanks, Mark the Words -- for demos and for checking the vertical end to end
 * on a fresh environment.
 *
 *     php artisan db:seed --class=H5PTextActivitySampleSeeder
 *
 * Scoping comes from the environment so this can be pointed at whatever
 * chapter the person running it actually has, matching the drag-and-drop
 * sample seeder beside it:
 *
 *     H5P_SAMPLE_TENANT, H5P_SAMPLE_STANDARD, H5P_SAMPLE_SUBJECT, H5P_SAMPLE_CHAPTER
 *
 * Each sample exercises the part of the grammar its type is most likely to get
 * wrong, rather than three variations of the same easy case:
 *
 *   1. Fill in the Blanks  -- alternatives AND a tip on one blank
 *                             (*Norway/Noreg:It is a Nordic country*). This is
 *                             the only type that renders the full grammar, so
 *                             it is the only one that can prove it round-trips.
 *   2. Drag the Words      -- three blanks plus two distractors, so the word
 *                             bank is larger than the number of slots and a
 *                             learner cannot succeed by elimination.
 *   3. Mark the Words      -- correct words that repeat elsewhere in the
 *                             passage unmarked ("ran" as a verb, "ran" inside
 *                             a noun phrase), which is where a naive
 *                             string-match scorer gives the wrong mark.
 *
 * The answer key is re-parsed from each passage rather than written by hand,
 * so the samples go through exactly the derivation a real save does -- a
 * sample that bypassed it would pass while the real path was broken.
 *
 * Re-running replaces the samples rather than adding more, so a demo database
 * does not accumulate copies. Only rows this seeder created are touched: they
 * are matched on title within the target chapter.
 */
class H5PTextActivitySampleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('h5p_text_activity')) {
            $this->command?->warn('h5p_text_activity does not exist yet. Run the migrations first.');

            return;
        }

        $scope = [
            'sub_institute_id' => (int) env('H5P_SAMPLE_TENANT', 1),
            'standard_id' => (int) env('H5P_SAMPLE_STANDARD', 1),
            'subject_id' => (int) env('H5P_SAMPLE_SUBJECT', 1),
            'chapter_id' => (int) env('H5P_SAMPLE_CHAPTER', 1),
        ];

        foreach ($this->samples() as $sample) {
            $this->seed($sample, $scope);
        }

        $this->command?->info(
            'Seeded 3 sample text activities (drag the words, fill in the blanks, mark the words) into chapter '
            . $scope['chapter_id'] . '.'
        );
    }

    /** @return list<array<string,mixed>> */
    private function samples(): array
    {
        return [
            [
                'content_type' => 'fill_in_the_blanks',
                'title' => 'Sample — Capitals of the Nordic countries',
                'task_description' => 'Type the missing country or city into each blank.',
                // The first blank carries both an alternative spelling and a
                // tip; the second carries two alternatives and no tip. Between
                // them they cover the whole grammar H5P.Blanks accepts.
                'passage' => "Oslo is the capital of *Norway/Noreg:It is a Nordic country*.\n"
                    . "The capital of Denmark is *Copenhagen/København*.\n"
                    . 'Reykjavik is the capital of *Iceland*.',
                'distractors' => '',
                'case_sensitive' => false,
                'accept_spelling_errors' => true,
                'pass_percentage' => 60,
            ],
            [
                'content_type' => 'drag_text',
                'title' => 'Sample — Parts of a plant cell',
                'task_description' => 'Drag each word into the sentence it belongs in.',
                'passage' => "The *chloroplast* is where photosynthesis happens.\n"
                    . "The *nucleus* holds the cell's genetic material.\n"
                    . 'The rigid *cell wall* gives a plant cell its shape.',
                // Two spare words. A learner with three slots and five words
                // cannot place the last one by elimination, which is the point.
                'distractors' => 'ribosome, mitochondrion',
                'pass_percentage' => 67,
            ],
            [
                'content_type' => 'mark_the_words',
                'title' => 'Sample — Find the verbs',
                'task_description' => 'Click every word that is a verb.',
                // "ran" appears twice: once as the verb to mark, once inside
                // "ran-down", which is not one. A scorer that matches on text
                // rather than on position marks both and is wrong.
                'passage' => 'The dog *ran* across the ran-down yard, *barked* twice, '
                    . 'and then *slept* under the old apple tree.',
                'distractors' => '',
                'pass_percentage' => 67,
            ],
        ];
    }

    /** @param array<string,mixed> $sample @param array<string,int> $scope */
    private function seed(array $sample, array $scope): void
    {
        $builder = app(H5PTextActivityBuilder::class);

        $existing = H5pTextActivity::withTrashed()
            ->where('content_type', $sample['content_type'])
            ->where('chapter_id', $scope['chapter_id'])
            ->where('sub_institute_id', $scope['sub_institute_id'])
            ->where('title', $sample['title'])
            ->first();

        if ($existing) {
            // Hard-delete the old key before rewriting, so a re-run does not
            // leave soft-deleted rows from every previous run behind.
            H5pTextActivityBlank::withTrashed()->where('text_activity_id', $existing->id)->forceDelete();
            $existing->forceDelete();
        }

        $activity = H5pTextActivity::create([
            'content_type' => $sample['content_type'],
            'title' => $sample['title'],
            'description' => 'Sample content shipped with the text-passage H5P types.',
            'task_description' => $sample['task_description'],
            'passage' => $sample['passage'],
            'distractors' => $sample['distractors'],
            'enable_retry' => true,
            'enable_show_solution' => true,
            'enable_check' => true,
            'case_sensitive' => $sample['case_sensitive'] ?? false,
            'accept_spelling_errors' => $sample['accept_spelling_errors'] ?? false,
            'instant_feedback' => false,
            'show_score_points' => true,
            'separate_lines' => false,
            'solution_requires_input' => true,
            'points_per_blank' => 1,
            'pass_percentage' => $sample['pass_percentage'],
            'feedback_bands' => [
                ['from' => 0, 'to' => 49, 'feedback' => 'Keep practising — read the passage again.'],
                ['from' => 50, 'to' => 99, 'feedback' => 'Good work. Check the ones you missed.'],
                ['from' => 100, 'to' => 100, 'feedback' => 'Everything correct.'],
            ],
            // Seeded samples are published: they exist to be opened, and a
            // draft would be invisible to the student surfaces they are meant
            // to demonstrate. Publish validation is re-checked below all the
            // same, so a sample can never ship in a state the app would refuse.
            'status' => 'published',
            'published_at' => now(),
            'library' => $builder->libraryVersionString($sample['content_type']),
            'standard_id' => $scope['standard_id'],
            'subject_id' => $scope['subject_id'],
            'chapter_id' => $scope['chapter_id'],
            'sub_institute_id' => $scope['sub_institute_id'],
            'created_by' => (int) env('H5P_SAMPLE_USER', 1),
            'created_at' => now(),
        ]);

        // Derived exactly as a real save derives it.
        foreach ($builder->parseAnswerKey($sample['content_type'], $activity->passage, $activity->distractors) as $slot) {
            H5pTextActivityBlank::create([
                'text_activity_id' => $activity->id,
                'blank_index' => $slot['blank_index'],
                'solution' => $slot['solution'],
                'alternatives' => $slot['alternatives'],
                'tip' => $slot['tip'],
                'is_distractor' => $slot['is_distractor'],
                'sub_institute_id' => $scope['sub_institute_id'],
                'created_by' => (int) env('H5P_SAMPLE_USER', 1),
                'created_at' => now(),
            ]);
        }

        $activity->load('blanks');

        if ($activity->blanks->where('is_distractor', false)->isEmpty()) {
            // The sample's own markup is broken. Say so loudly rather than
            // leaving an unscorable activity published on a demo database.
            $this->command?->error('Sample "' . $sample['title'] . '" parsed to no answers — check its markup.');
            $activity->update(['status' => 'draft', 'published_at' => null]);

            return;
        }

        $activity->forceFill([
            'content_json' => json_encode($builder->build($activity), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ])->saveQuietly();

        $this->command?->line(sprintf(
            '  %-22s %s (%d answers, %d distractors, max score %d)',
            $sample['content_type'],
            $sample['title'],
            $activity->blanks->where('is_distractor', false)->count(),
            $activity->blanks->where('is_distractor', true)->count(),
            $activity->maxScore()
        ));
    }
}
