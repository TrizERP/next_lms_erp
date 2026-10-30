<?php

namespace Tests\Feature\QuestionPapers;

use App\Http\Controllers\api\Platform\QuestionPaperController;
use App\Services\QuestionPapers\ActivationRefused;
use App\Services\QuestionPapers\QuestionPaperChapterMatcher;
use App\Services\QuestionPapers\QuestionPaperJobService;
use App\Services\QuestionPapers\QuestionPaperMetadataResolver;
use App\Services\QuestionPapers\QuestionPaperReviewService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subject and chapter resolution of an uploaded question paper, on isolated data.
 *
 * The school here has the traps the real one has: a subject named "SCIENCE" that no standard uses beside the mapped
 * "Science", the same subject name under an unrelated standard, and several chapters with curriculum text of their own.
 */
class QuestionPaperSubjectChapterResolutionTest extends QuestionPaperTestCase
{
    private const CELL = 102;
    private const MOTION = 103;
    private const MATTER = 101;

    private const HEADING = ['standard' => '9', 'subject' => 'SCIENCE', 'chapter' => null, 'academic_year' => null];

    protected function createSurroundingTables(): void
    {
        parent::createSurroundingTables();

        Schema::table('chapter_master', function ($t) {
            $t->text('key_concepts')->nullable();
            $t->text('chapter_desc')->nullable();
        });
        Schema::create('topic_master', function ($t) {
            $t->increments('id'); $t->integer('chapter_id'); $t->string('name')->nullable(); $t->text('description')->nullable();
        });
        Schema::create('lms_concept', function ($t) {
            $t->increments('id'); $t->integer('chapter_id'); $t->string('name')->nullable(); $t->text('description')->nullable(); $t->text('definition')->nullable();
        });
        Schema::create('lms_learning_outcomes', function ($t) {
            $t->increments('id'); $t->integer('chapter_id'); $t->text('description')->nullable();
        });
        Schema::create('semantic_intelligence', function ($t) {
            $t->increments('id'); $t->integer('chapter_id'); $t->integer('sub_institute_id')->nullable();
            $t->text('learning_objective')->nullable(); $t->text('knowledge')->nullable(); $t->text('learning_outcomes')->nullable();
        });

        // Standards and subjects: 14 "SCIENCE" is mapped to nothing; 33 "Science" belongs to Class 10 only.
        DB::table('standard')->insert(['id' => 10, 'name' => 'Class 10', 'sub_institute_id' => self::TENANT]);
        DB::table('subject')->insert([
            ['id' => 14, 'subject_name' => 'SCIENCE', 'sub_institute_id' => self::TENANT],
            ['id' => 33, 'subject_name' => 'Science', 'sub_institute_id' => self::TENANT],
            ['id' => 4, 'subject_name' => 'Mathematics', 'sub_institute_id' => self::TENANT],
        ]);
        DB::table('sub_std_map')->insert([
            ['subject_id' => 3, 'standard_id' => 9, 'status' => 1],
            ['subject_id' => 4, 'standard_id' => 9, 'status' => 1],
            ['subject_id' => 33, 'standard_id' => 10, 'status' => 1],
        ]);

        // Chapters of Class 9 Science, each with its own curriculum text. 101 exists already (title only).
        DB::table('chapter_master')->where('id', 101)->update([
            'key_concepts' => json_encode([['name' => 'States of matter', 'description' => 'solids, liquids and gases differ in the arrangement and movement of particles'], ['name' => 'Evaporation and cooling', 'description' => 'a liquid cools as its fastest particles escape as vapour']]),
            'chapter_desc' => 'Particle nature of matter, change of state, melting, boiling, condensation and latent heat.',
        ]);
        DB::table('chapter_master')->insert([
            ['id' => self::CELL, 'standard_id' => 9, 'subject_id' => 3, 'chapter_name' => 'The Cell: Basic Unit of Life', 'sub_institute_id' => self::TENANT,
                'key_concepts' => json_encode([['name' => 'Cell organelles', 'description' => 'nucleus controls the cell, mitochondria release energy, the membrane controls what enters'], ['name' => 'Cytoplasm', 'description' => 'jelly-like substance holding the organelles']]),
                'chapter_desc' => 'Structure and function of plant and animal cells, nucleus, membrane, cytoplasm and mitochondria.'],
            ['id' => self::MOTION, 'standard_id' => 9, 'subject_id' => 3, 'chapter_name' => 'Motion and Force', 'sub_institute_id' => self::TENANT,
                'key_concepts' => json_encode([['name' => 'Velocity and acceleration', 'description' => 'velocity is displacement per unit time; acceleration is the change in velocity per unit time'], ['name' => 'Newton laws of motion', 'description' => 'inertia, force and momentum']]),
                'chapter_desc' => 'Speed, velocity, acceleration, uniform motion, force, inertia and the laws of motion.'],
            // Another subject of the same standard, and the same subject of another standard: never candidates.
            ['id' => 401, 'standard_id' => 9, 'subject_id' => 4, 'chapter_name' => 'Linear Equations', 'sub_institute_id' => self::TENANT,
                'key_concepts' => json_encode([['name' => 'Equations', 'description' => 'solve linear equations in one variable']]), 'chapter_desc' => 'Algebra: variables, equations, solving.'],
            ['id' => 301, 'standard_id' => 10, 'subject_id' => 33, 'chapter_name' => 'Light and Reflection', 'sub_institute_id' => self::TENANT,
                'key_concepts' => json_encode([['name' => 'Mirrors', 'description' => 'nucleus of a cell is not here; reflection by concave and convex mirrors']]), 'chapter_desc' => 'Light, mirrors, reflection and refraction.'],
        ]);
        DB::table('topic_master')->insert([
            ['chapter_id' => self::CELL, 'name' => 'Cell theory and organelles', 'description' => 'nucleus, mitochondria, ribosomes'],
            ['chapter_id' => self::MOTION, 'name' => 'Describing motion', 'description' => 'distance, displacement, speed, velocity, acceleration'],
            ['chapter_id' => self::MATTER, 'name' => 'Change of state', 'description' => 'melting, freezing, evaporation, condensation'],
        ]);
        DB::table('lms_concept')->insert([
            ['chapter_id' => self::CELL, 'name' => 'Mitochondria', 'description' => 'organelle that releases energy for the cell', 'definition' => 'powerhouse of the cell'],
            ['chapter_id' => self::MOTION, 'name' => 'Acceleration', 'description' => 'rate of change of velocity', 'definition' => 'change in velocity per unit time'],
            ['chapter_id' => self::MATTER, 'name' => 'Latent heat', 'description' => 'heat absorbed during change of state', 'definition' => 'energy for melting or evaporation without a temperature change'],
        ]);
        DB::table('lms_learning_outcomes')->insert([
            ['chapter_id' => self::CELL, 'description' => 'Describe the function of the nucleus, membrane and mitochondria in a cell.'],
            ['chapter_id' => self::MOTION, 'description' => 'Calculate velocity and acceleration of a moving object.'],
            ['chapter_id' => self::MATTER, 'description' => 'Explain why evaporation causes cooling and how particles change state.'],
        ]);
    }

    /* ---------- helpers ---------- */

    private function resolve(array $header, array $jobFields = []): array
    {
        $job = (object) ($jobFields + ['sub_institute_id' => self::TENANT, 'standard_id' => null, 'subject_id' => null, 'chapter_id' => null, 'academic_year' => null]);

        return app(QuestionPaperMetadataResolver::class)->resolve($job, $header);
    }

    private function short(string $text, string $number = '1'): array
    {
        return ['item_number' => $number, 'page' => 1, 'format' => 'short', 'question_text' => $text, 'answer_text' => 'An answer.', 'marks' => 2];
    }

    private function cellQ(string $n = '1'): array
    {
        return $this->short('What is the function of the nucleus in a cell?', $n);
    }

    private function motionQ(string $n = '2'): array
    {
        return $this->short('Define acceleration and write its unit.', $n);
    }

    private function matterQ(string $n = '3'): array
    {
        return $this->short('Why does evaporation cause cooling of a liquid?', $n);
    }

    private function chapterOf(string $titleLike): ?int
    {
        $value = DB::table('lms_question_master')->where('question_title', 'like', "%{$titleLike}%")->value('chapter_id');

        return $value === null ? null : (int) $value;
    }

    /* ---------- standard -> subject ---------- */

    public function test_the_standard_resolves_and_the_science_heading_picks_the_subject_mapped_to_that_standard(): void
    {
        $r = $this->resolve(self::HEADING);

        $this->assertSame(9, $r['standard_id']);
        $this->assertSame(3, $r['subject_id'], 'SCIENCE resolves to the Science that Class 9 uses, not to the unmapped SCIENCE (14) or Class 10\'s Science (33)');
        $this->assertArrayNotHasKey('subject', $r['candidates']);
    }

    public function test_subject_matching_ignores_case_whitespace_punctuation_and_class_prefixes(): void
    {
        // Letter-spaced titles ("S C I E N C E") are formatting too: spacing and punctuation never change which subject it is.
        foreach (['SCIENCE', 'science', '  Science  ', 'Science.', 'S C I E N C E', 'Class 9 - Science', 'Std. IX: SCIENCE', 'Subject: Science', '9 - SCIENCE', "Science\u{00A0}"] as $heading) {
            $this->assertSame(3, $this->resolve(['subject' => $heading] + self::HEADING)['subject_id'], "heading \"{$heading}\"");
        }
        // ...but a different word is a different subject, however it is formatted.
        $this->assertNull($this->resolve(['subject' => 'Social Science'] + self::HEADING)['subject_id']);
    }

    public function test_the_same_subject_name_under_another_standard_is_not_a_candidate(): void
    {
        // Class 10's "Science" is subject 33, Class 9's is 3: each standard sees only its own.
        $this->assertSame(33, $this->resolve(['standard' => 'Class 10', 'subject' => 'Science'] + self::HEADING)['subject_id']);
        $this->assertSame(3, $this->resolve(['standard' => 'Class 9', 'subject' => 'Science'] + self::HEADING)['subject_id']);

        // A subject only another standard uses is not valid here: unresolved, with this standard's valid subjects offered.
        $r = $this->resolve(['standard' => 'Class 10', 'subject' => 'Mathematics'] + self::HEADING);
        $this->assertNull($r['subject_id']);
        $this->assertSame([33], array_column($r['candidates']['subject'], 'id'));
        $this->assertStringContainsString('matches no subject mapped to this standard', implode(' ', $r['notes']));
    }

    public function test_only_active_mappings_count(): void
    {
        DB::table('sub_std_map')->where('subject_id', 3)->where('standard_id', 9)->update(['status' => 0]);
        $inactive = $this->resolve(self::HEADING);
        $this->assertNull($inactive['subject_id'], 'an inactive mapping is not a valid subject for the standard');

        DB::table('sub_std_map')->where('subject_id', 3)->where('standard_id', 9)->update(['status' => 1, 'deleted_at' => now()]);
        $this->assertNull($this->resolve(self::HEADING)['subject_id'], 'a deleted mapping is not valid either');
    }

    public function test_a_heading_may_use_the_mappings_display_name(): void
    {
        Schema::table('sub_std_map', fn ($t) => $t->string('display_name')->nullable());
        DB::table('sub_std_map')->where('subject_id', 4)->update(['display_name' => 'Maths']);

        $this->assertSame(4, $this->resolve(['subject' => 'MATHS'] + self::HEADING)['subject_id']);
    }

    public function test_when_two_subjects_of_the_standard_match_the_teacher_is_offered_exactly_those(): void
    {
        DB::table('sub_std_map')->insert(['subject_id' => 14, 'standard_id' => 9, 'status' => 1]); // now SCIENCE is mapped too

        $r = $this->resolve(self::HEADING);

        $this->assertNull($r['subject_id']);
        $ids = array_column($r['candidates']['subject'], 'id');
        sort($ids);
        $this->assertSame([3, 14], $ids);
    }

    public function test_a_missing_subject_heading_resolves_and_assigns_subject_from_question_secondary_evidence(): void
    {
        $this->fakeParser([['header' => ['subject' => null] + self::HEADING, 'questions' => [$this->cellQ('1'), $this->motionQ('2'), $this->matterQ('3')]]]);
        $job = $this->makeJob();

        $this->process($job);

        $row = $this->job($job);
        $this->assertSame(3, (int) $row->subject_id, 'subject is resolved from question secondary evidence when heading is missing');
        $this->assertSame(9, (int) $row->standard_id);
        $this->assertSame(3, DB::table('lms_question_master')->count(), 'questions are saved under resolved subject');
        $this->assertSame([self::CELL, self::MOTION, self::MATTER], [$this->chapterOf('nucleus'), $this->chapterOf('acceleration'), $this->chapterOf('evaporation')]);
    }

    public function test_no_matching_subject_keeps_the_questions_and_asks_for_manual_resolution(): void
    {
        $this->fakeParser([['header' => ['subject' => 'History'] + self::HEADING, 'questions' => [$this->cellQ()]]]);
        $job = $this->makeJob();

        $this->process($job);

        $row = $this->job($job);
        $this->assertSame('completed_with_warnings', $row->status);
        $this->assertNull($row->subject_id);
        $this->assertSame(1, DB::table('question_paper_items')->where('job_id', $job->id)->where('state', 'unresolved')->count(), 'the extracted question is preserved');
        $this->assertTrue(app(QuestionPaperJobService::class)->present($row)['can_retry']);
    }

    /* ---------- chapters ---------- */

    public function test_the_chapter_is_found_from_the_question_when_the_pdf_names_none(): void
    {
        $this->fakeParser([['header' => self::HEADING, 'questions' => [$this->cellQ()]]]);
        $job = $this->makeJob();

        $this->process($job);

        $row = DB::table('lms_question_master')->first();
        $this->assertSame([9, 3, self::CELL, 1], [(int) $row->standard_id, (int) $row->subject_id, (int) $row->chapter_id, (int) $row->status]);
        $this->assertNull($this->job($job)->chapter_id, 'no paper-wide chapter was invented');
        $payload = json_decode(DB::table('question_paper_items')->value('payload'), true);
        $this->assertSame('auto', $payload['chapter_source']);
        $this->assertSame('high', $payload['chapter_match']['level']);
        $this->assertSame(99, $payload['chapter_match']['confidence']);
        $this->assertNotEmpty($payload['chapter_match']['reason']);
    }

    public function test_questions_from_different_chapters_in_one_paper_are_resolved_independently(): void
    {
        $this->fakeParser([['header' => self::HEADING, 'questions' => [$this->cellQ('1'), $this->motionQ('2'), $this->matterQ('3')]]]);
        $job = $this->makeJob();

        $this->process($job);

        $this->assertSame(self::CELL, $this->chapterOf('nucleus'));
        $this->assertSame(self::MOTION, $this->chapterOf('acceleration'));
        $this->assertSame(self::MATTER, $this->chapterOf('evaporation'));
        $this->assertSame(3, DB::table('lms_question_master')->where('status', 1)->count());
        $this->assertSame('completed', $this->job($job)->status);
    }

    public function test_chapters_of_other_subjects_and_standards_are_never_candidates(): void
    {
        // "nucleus of a cell" also appears in Class 10's chapter 301 text, and Linear Equations (subject 4) exists:
        // neither may ever be chosen for a Class 9 Science question.
        $matcher = app(QuestionPaperChapterMatcher::class);

        $names = $matcher->chapterNames(self::TENANT, 9, 3);

        $this->assertSame([self::MATTER, self::CELL, self::MOTION], array_keys($names));
        $suggestion = $matcher->suggest(self::TENANT, 9, 3, [0 => $this->cellQ()])[0];
        $this->assertSame(self::CELL, $suggestion['suggested_id']);
        $this->assertNotContains(301, array_column($suggestion['alternatives'], 'id'));
    }

    public function test_ambiguous_and_weak_matches_are_suggestions_only_and_the_question_waits_for_a_teacher(): void
    {
        $this->fakeParser([['header' => self::HEADING, 'questions' => [
            // wording from two chapters at once: no clear lead
            $this->short('Write one fact about the nucleus and one fact about velocity.', '1'),
            // nothing about any chapter
            $this->short('Describe the apparatus shown in the diagram and label its parts.', '2'),
        ]]]);
        $job = $this->makeJob();

        $this->process($job);

        $this->assertSame(0, DB::table('lms_question_master')->where('status', 1)->count(), 'nothing is activated on a doubtful chapter');
        $this->assertSame(2, DB::table('lms_question_master')->where('status', 0)->whereNull('chapter_id')->count());
        $row = $this->job($job);
        $this->assertSame('completed_with_warnings', $row->status);
        $this->assertSame(2, (int) $row->questions_needing_review);
        $this->assertStringContainsString('2 question(s) have no confirmed chapter', implode(' ', app(QuestionPaperJobService::class)->present($row)['metadata_notes']));

        $items = collect(app(QuestionPaperReviewService::class)->items($row));
        $this->assertTrue($items->every(fn ($i) => $i['chapter_id'] === null && $i['needs_chapter'] === true));
        $first = $items->first()['chapter_suggestion'];
        $this->assertNotNull($first, 'the best guess is shown');
        $this->assertFalse($first['assigned']);
        $this->assertContains($first['level'], ['close', 'weak']);
        $this->assertNull($items->last()['chapter_suggestion'], 'a question that matches nothing gets no suggestion at all');
    }

    public function test_the_decision_rule_needs_a_strong_score_a_clear_lead_and_something_to_compare_with(): void
    {
        $matcher = app(QuestionPaperChapterMatcher::class);
        $names = [1 => 'One', 2 => 'Two', 3 => 'Three'];
        $rank = fn (float $a, float $b) => [['chapter_id' => 1, 'score' => $a, 'terms' => ['x']], ['chapter_id' => 2, 'score' => $b, 'terms' => ['y']]];

        $this->assertTrue($matcher->decide($rank(0.100, 0.040), $names)['auto'], 'strong and clearly ahead');
        $this->assertFalse($matcher->decide($rank(0.059, 0.010), $names)['auto'], 'just under the score floor');
        $this->assertTrue($matcher->decide($rank(0.060, 0.010), $names)['auto'], 'exactly on the floor');
        $this->assertFalse($matcher->decide($rank(0.100, 0.080), $names)['auto'], 'lead of 20% is under 25%');
        $this->assertTrue($matcher->decide($rank(0.100, 0.075), $names)['auto'], 'lead of exactly 25%');
        $this->assertSame('close', $matcher->decide($rank(0.100, 0.090), $names)['level']);
        $this->assertSame('weak', $matcher->decide($rank(0.040, 0.010), $names)['level']);
        $this->assertSame('none', $matcher->decide($rank(0.020, 0.000), $names)['level']);
        $this->assertSame('none', $matcher->decide([], $names)['level']);

        $only = $matcher->decide([['chapter_id' => 1, 'score' => 0.5, 'terms' => ['x']]], [1 => 'Only']);
        $this->assertFalse($only['auto'], 'with a single chapter there is nothing to compare with');
        $this->assertStringContainsString('Only one chapter', $only['reason']);
    }

    public function test_a_chapter_chosen_for_the_whole_paper_beats_the_matcher(): void
    {
        $this->fakeParser([['header' => self::HEADING, 'questions' => [$this->cellQ()]]]);
        $job = $this->makeJob(academic: ['standard_id' => 9, 'subject_id' => 3, 'chapter_id' => self::MOTION]);

        $this->process($job);

        $this->assertSame(self::MOTION, $this->chapterOf('nucleus'), 'the uploader\'s explicit choice stands even though the wording says Cell');
        $payload = json_decode(DB::table('question_paper_items')->value('payload'), true);
        $this->assertArrayNotHasKey('chapter_source', $payload);
    }

    /* ---------- retry, resume, idempotency ---------- */

    public function test_a_metadata_only_retry_does_not_reextract_or_reparse_and_saves_each_question_under_its_own_chapter(): void
    {
        $parser = $this->fakeParser([['header' => ['subject' => 'History'] + self::HEADING, 'questions' => [$this->cellQ('1'), $this->motionQ('2'), $this->matterQ('3')]]]);
        $job = $this->makeJob();
        $this->process($job);
        $first = $this->job($job);
        $this->assertSame(0, DB::table('lms_question_master')->count());
        $chunks = [(int) $first->total_chunks, (int) $first->parsed_chunks, (int) $first->total_pages, (int) $first->processed_pages];

        // The teacher picks the subject from the valid options.
        $this->assertTrue(app(QuestionPaperJobService::class)->prepareManualRetry($first, ['standard_id' => 9, 'subject_id' => 3]));
        // The stored PDF is gone: a retry that touched it would fail. It must not need it.
        $this->files = [];
        $this->process($job);

        $this->assertSame(1, $parser->calls, 'the AI parser was not called again');
        $row = $this->job($job);
        $this->assertSame($chunks, [(int) $row->total_chunks, (int) $row->parsed_chunks, (int) $row->total_pages, (int) $row->processed_pages], 'extraction progress is untouched');
        $this->assertSame(3, DB::table('lms_question_master')->count());
        $this->assertSame([self::CELL, self::MOTION, self::MATTER], [$this->chapterOf('nucleus'), $this->chapterOf('acceleration'), $this->chapterOf('evaporation')]);
        $this->assertSame(3, DB::table('lms_question_master')->where('subject_id', 3)->where('standard_id', 9)->count());
    }

    public function test_a_retry_updates_inactive_questions_in_place_and_never_erases_the_chapters_they_already_have(): void
    {
        // Q1 is a cell question without a printed key: saved INACTIVE, but its chapter is matched. Q2 matches nothing.
        $parser = $this->fakeParser([['header' => self::HEADING, 'questions' => [
            $this->mcq('What is the function of the nucleus in a cell?', null),
            $this->short('Describe the apparatus shown in the diagram and label its parts.', '2'),
        ]]]);
        $job = $this->makeJob();
        $this->process($job);
        $ids = DB::table('lms_question_master')->orderBy('id')->pluck('id')->all();
        $this->assertSame(self::CELL, (int) DB::table('lms_question_master')->where('id', $ids[0])->value('chapter_id'));
        $this->assertNull(DB::table('lms_question_master')->where('id', $ids[1])->value('chapter_id'));

        // A retry that restates standard and subject (no chapter) used to write NULL over every existing question.
        $this->assertTrue(app(QuestionPaperJobService::class)->prepareManualRetry($this->job($job), ['standard_id' => 9, 'subject_id' => 3]));
        $this->process($job);

        $this->assertSame($ids, DB::table('lms_question_master')->orderBy('id')->pluck('id')->all(), 'same rows: updated in place, nothing inserted');
        $this->assertSame(self::CELL, (int) DB::table('lms_question_master')->where('id', $ids[0])->value('chapter_id'), 'the chapter it had is kept');
        $this->assertSame(1, $parser->calls);
        $this->assertSame(0, DB::table('lms_question_master')->where('status', 1)->count(), 'in-place updates do not activate anything');
    }

    public function test_processing_the_same_paper_twice_cannot_duplicate_questions_or_answer_rows(): void
    {
        $questions = [$this->mcq('What is the function of the nucleus in a cell?', 'A'), $this->motionQ('2'), $this->matterQ('3')];
        $this->fakeParser([['header' => self::HEADING, 'questions' => $questions], ['header' => self::HEADING, 'questions' => $questions]]);
        $job = $this->makeJob();
        $this->process($job);
        $count = [DB::table('lms_question_master')->count(), DB::table('answer_master')->count()];

        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'queued', 'parsed_chunks' => 0, 'total_chunks' => null, 'finished_at' => null]);
        $this->process($job);

        $this->assertSame($count, [DB::table('lms_question_master')->count(), DB::table('answer_master')->count()]);
        $this->assertSame(3, DB::table('question_paper_items')->where('job_id', $job->id)->count());
    }

    /* ---------- review and activation ---------- */

    private function doubtfulPaper(): object
    {
        $this->fakeParser([['header' => self::HEADING, 'questions' => [$this->short('Describe the apparatus shown in the diagram and label its parts.', '1')]]]);
        $job = $this->makeJob();
        $this->process($job);

        return $this->job($job);
    }

    public function test_a_teacher_confirms_a_chapter_at_activation_and_only_a_chapter_of_this_standard_and_subject_is_accepted(): void
    {
        $job = $this->doubtfulPaper();
        $itemId = (int) DB::table('question_paper_items')->where('job_id', $job->id)->value('id');
        $review = app(QuestionPaperReviewService::class);

        $none = $review->activate($job, [['id' => $itemId]]);
        $this->assertFalse($none[0]['ok']);
        $this->assertStringContainsString('Confirm the chapter', $none[0]['message']);

        foreach ([301, 401, 9999] as $foreign) { // Class 10 Science, Class 9 Mathematics, nothing
            $refused = $review->activate($job, [['id' => $itemId, 'chapter_id' => $foreign]]);
            $this->assertFalse($refused[0]['ok'], "chapter {$foreign}");
        }
        $this->assertSame(0, DB::table('lms_question_master')->where('status', 1)->count());

        $done = $review->activate($job, [['id' => $itemId, 'chapter_id' => self::MOTION]]);
        $this->assertTrue($done[0]['ok']);
        $row = DB::table('lms_question_master')->first();
        $this->assertSame([1, self::MOTION, 9, 3], [(int) $row->status, (int) $row->chapter_id, (int) $row->standard_id, (int) $row->subject_id]);
        $payload = json_decode(DB::table('question_paper_items')->where('id', $itemId)->value('payload'), true);
        $this->assertSame('teacher', $payload['chapter_source']);
    }

    public function test_the_review_api_exposes_the_suggestion_the_confidence_and_who_chose_the_chapter(): void
    {
        $this->fakeParser([['header' => self::HEADING, 'questions' => [$this->cellQ('1'), $this->short('Describe the apparatus shown in the diagram and label its parts.', '2')]]]);
        $job = $this->makeJob();
        $this->process($job);
        $controller = app(QuestionPaperController::class);

        $items = $controller->items($this->authed('GET'), $job->uuid)->getData(true)['data']['items'];

        $auto = $items[0];
        $this->assertSame([self::CELL, 'The Cell: Basic Unit of Life', 'auto', false], [$auto['chapter_id'], $auto['chapter_name'], $auto['chapter_source'], $auto['needs_chapter']]);
        $this->assertSame(99, $auto['chapter_suggestion']['confidence']);
        $this->assertTrue($auto['chapter_suggestion']['assigned']);
        $this->assertNotEmpty($auto['chapter_suggestion']['alternatives']);

        $waiting = $items[1];
        $this->assertNull($waiting['chapter_id']);
        $this->assertTrue($waiting['needs_chapter']);

        $detail = $controller->show($this->authed('GET'), $job->uuid)->getData(true)['data'];
        $this->assertSame(['id' => 9, 'name' => 'Class 9'], $detail['academic']['standard']);
        $this->assertSame(['id' => 3, 'name' => 'Science'], $detail['academic']['subject']);
        $this->assertNull($detail['academic']['chapter'], 'the paper as a whole has no chapter; each question has its own');
    }

    public function test_the_activation_endpoint_accepts_a_chapter_per_question(): void
    {
        $job = $this->doubtfulPaper();
        $itemId = (int) DB::table('question_paper_items')->where('job_id', $job->id)->value('id');

        $response = app(QuestionPaperController::class)->activate($this->authed('POST', ['items' => [['id' => $itemId, 'chapter_id' => self::CELL]]]), $job->uuid);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['data']['results'][0]['ok']);
        $this->assertSame(self::CELL, (int) DB::table('lms_question_master')->value('chapter_id'));
    }
}
