<?php

namespace Tests\Feature\QuestionPapers;

use App\Http\Controllers\api\Platform\QuestionPaperController;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use App\Services\QuestionPapers\LeaseLostException;
use App\Services\QuestionPapers\QuestionPaperJobService;
use App\Services\QuestionPapers\QuestionPaperMetadataResolver;
use App\Services\QuestionPapers\QuestionPaperReviewService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuestionPaperReviewTest extends QuestionPaperTestCase
{
    private const HEADER = ['standard' => 'Class 9', 'subject' => 'Science', 'chapter' => 'Matter in Our Surroundings', 'academic_year' => null];

    /* ---------- the MySQL lease/progress regression ---------- */

    public function test_a_progress_write_that_changes_no_rows_is_not_treated_as_a_lost_lease(): void
    {
        $job = $this->makeJob();
        $jobs = new class extends QuestionPaperJobService {
            // MySQL reports rows CHANGED: an update that rewrites identical values reports 0 although the row matched.
            protected function applyLeasedUpdate(int $id, string $token, array $fields): int
            {
                return 0;
            }
        };
        $token = $jobs->acquireLease((int) $job->id);

        $jobs->update((int) $job->id, $token, ['stage' => 'validating']); // must not throw: the lease is still ours
        $this->assertTrue(true);
    }

    public function test_zero_changed_rows_with_a_genuinely_lost_lease_still_stops_the_worker(): void
    {
        $job = $this->makeJob();
        $jobs = new class extends QuestionPaperJobService {
            protected function applyLeasedUpdate(int $id, string $token, array $fields): int
            {
                return 0;
            }
        };
        $token = $jobs->acquireLease((int) $job->id);
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['lease_token' => 'taken-over']);

        $this->expectException(LeaseLostException::class);
        $jobs->update((int) $job->id, $token, ['stage' => 'validating']);
    }

    /** On MariaDB/MySQL (QP_MYSQL_PORT) this is the exact first-write sequence that failed in the live run. */
    public function test_the_first_progress_write_after_taking_the_lease_succeeds(): void
    {
        $job = $this->makeJob();
        $jobs = app(QuestionPaperJobService::class);
        $token = $jobs->acquireLease((int) $job->id);

        $jobs->update((int) $job->id, $token, ['stage' => 'validating']);
        $jobs->update((int) $job->id, $token, ['stage' => 'validating']);

        $this->assertSame('validating', DB::table('question_paper_jobs')->where('id', $job->id)->value('stage'));
    }

    /* ---------- academic resolution ---------- */

    private function twoScienceSubjects(bool $mapBoth = false, bool $mapNone = false): void
    {
        DB::table('subject')->insert(['id' => 14, 'subject_name' => 'SCIENCE', 'sub_institute_id' => self::TENANT]);
        if (!$mapNone) {
            DB::table('sub_std_map')->insert(['subject_id' => 3, 'standard_id' => 9, 'status' => 1]);
        }
        if ($mapBoth) {
            DB::table('sub_std_map')->insert(['subject_id' => 14, 'standard_id' => 9, 'status' => 1]);
        }
    }

    private function resolve(array $header, array $jobFields = []): array
    {
        $job = (object) ($jobFields + ['sub_institute_id' => self::TENANT, 'standard_id' => null, 'subject_id' => null, 'chapter_id' => null, 'academic_year' => null]);

        return app(QuestionPaperMetadataResolver::class)->resolve($job, $header);
    }

    public function test_same_named_subjects_are_settled_by_the_standards_mapping_never_by_capitalisation(): void
    {
        $this->twoScienceSubjects();

        $r = $this->resolve(self::HEADER);

        $this->assertSame([9, 3], [$r['standard_id'], $r['subject_id']]);
        $this->assertSame([], $r['candidates']['subject'] ?? []);

        // The heading's capitalisation does not change the outcome: "SCIENCE" still resolves to the mapped subject 3.
        $this->assertSame(3, $this->resolve(['subject' => 'SCIENCE'] + self::HEADER)['subject_id']);
    }

    public function test_subjects_stay_ambiguous_when_the_mapping_cannot_decide_and_candidates_are_reported(): void
    {
        foreach ([['mapBoth' => true], ['mapNone' => true]] as $case) {
            DB::table('subject')->where('id', 14)->delete();
            DB::table('sub_std_map')->delete();
            $this->twoScienceSubjects(...$case);

            $r = $this->resolve(self::HEADER);

            $this->assertNull($r['subject_id'], json_encode($case));
            if ($case['mapBoth'] ?? false) {
                $ids = array_column($r['candidates']['subject'], 'id');
                sort($ids); // the order of same-named subjects is the database's collation, not part of the rule
                $this->assertSame([3, 14], $ids);
                $this->assertStringContainsString('more than one subject', implode(' ', $r['notes']));
            } else {
                $this->assertArrayNotHasKey('subject', $r['candidates']);
                $this->assertStringContainsString('no subject mapped', implode(' ', $r['notes']));
            }
        }
    }

    public function test_without_a_resolved_standard_an_ambiguous_subject_is_not_guessed(): void
    {
        $this->twoScienceSubjects();

        $r = $this->resolve(['standard' => 'Grade Nine'] + self::HEADER);

        $this->assertNull($r['standard_id']);
        $this->assertNull($r['subject_id']);
    }

    public function test_the_uploaders_choice_beats_the_heading_and_other_schools_rows_are_never_candidates(): void
    {
        $this->twoScienceSubjects(mapBoth: true);

        $r = $this->resolve(self::HEADER, ['standard_id' => 9, 'subject_id' => 14]);

        $this->assertSame(14, $r['subject_id']);
        $this->assertSame([], $r['candidates']);
        // Subject 13 is another school's "Science": it never appears among this school's candidates.
        $this->assertNotContains(13, array_column($this->resolve(self::HEADER)['candidates']['subject'] ?? [], 'id'));
    }

    public function test_a_chapter_is_resolved_only_when_stated_and_unique_and_never_invented(): void
    {
        $this->assertSame(101, $this->resolve(self::HEADER)['chapter_id']);

        $none = $this->resolve(['chapter' => null] + self::HEADER);
        $this->assertNull($none['chapter_id']);
        $this->assertStringContainsString('does not state a chapter', implode(' ', $none['notes']));

        $unknown = $this->resolve(['chapter' => 'Atoms'] + self::HEADER);
        $this->assertNull($unknown['chapter_id']);

        DB::table('chapter_master')->insert(['id' => 102, 'standard_id' => 9, 'subject_id' => 3, 'chapter_name' => 'matter  in our surroundings', 'sub_institute_id' => self::TENANT]);
        $twice = $this->resolve(self::HEADER);
        $this->assertNull($twice['chapter_id']);
        $this->assertSame([101, 102], array_column($twice['candidates']['chapter'], 'id'));
    }

    public function test_the_options_endpoint_marks_which_subject_the_standard_uses(): void
    {
        $this->twoScienceSubjects();

        $data = app(QuestionPaperController::class)->options($this->authed('GET', ['standard_id' => 9]))->getData(true)['data'];
        $mapped = collect($data['subjects'])->pluck('mapped', 'id')->all();

        $this->assertSame([3], array_keys($mapped));
        $this->assertTrue($mapped[3]);
    }

    public function test_an_ambiguous_paper_waits_for_the_owner_then_resumes_without_reparsing(): void
    {
        DB::table('sub_std_map')->delete();
        $this->twoScienceSubjects(mapNone: true);
        $parser = $this->fakeParser([['header' => self::HEADER, 'questions' => [$this->mcq('Which state of matter has a definite shape?', 'A')]]]);
        $job = $this->makeJob();
        $this->process($job);

        $row = $this->job($job);
        $present = app(QuestionPaperJobService::class)->present($row);
        $this->assertSame('completed_with_warnings', $row->status);
        $this->assertTrue($present['can_retry']);
        $this->assertSame(0, DB::table('lms_question_master')->count());
        $context = app(QuestionPaperJobService::class)->academicContext($row);
        $this->assertSame(['id' => 9, 'name' => 'Class 9'], $context['standard']);
        $this->assertNull($context['subject']);
        $this->assertSame([], (array) ($context['candidates']->subject ?? []));

        $this->assertTrue(app(QuestionPaperJobService::class)->prepareManualRetry($row, ['subject_id' => 3]));
        $this->process($job);

        $this->assertSame(1, $parser->calls, 'the resumed run does not call the AI again');
        $this->assertSame(1, DB::table('lms_question_master')->count());
        $this->assertSame(3, (int) DB::table('lms_question_master')->value('subject_id'));
    }

    /* ---------- every format ---------- */

    public function test_each_question_format_is_saved_with_its_own_code_and_type(): void
    {
        $opts = [['label' => 'A', 'text' => 'Alpha'], ['label' => 'B', 'text' => 'Beta'], ['label' => 'C', 'text' => 'Gamma']];
        $questions = [
            ['format' => 'mcq', 'question_text' => 'Pick the first Greek letter.', 'options' => $opts, 'correct_option' => 'A'],
            ['format' => 'true_false', 'question_text' => 'Water freezes at zero degrees Celsius.', 'answer_text' => 'True'],
            ['format' => 'fill_blank', 'question_text' => 'The boiling point of water is ____ degrees Celsius.', 'answer_text' => '100'],
            ['format' => 'match_following', 'question_text' => 'Match the state with its example.', 'pairs' => [['left' => 'Solid', 'right' => 'Ice'], ['left' => 'Gas', 'right' => 'Steam']]],
            ['format' => 'assertion_reason', 'question_text' => 'Assertion: ice floats. Reason: ice is less dense.', 'options' => $opts, 'correct_option' => 'B'],
            ['format' => 'numerical', 'question_text' => 'Find the density of 10 g in 2 cm3.', 'answer_text' => '5 g/cm3'],
            ['format' => 'very_short', 'question_text' => 'Name the process of liquid turning to gas.', 'answer_text' => 'Evaporation'],
            ['format' => 'short', 'question_text' => 'Explain why steam burns are severe.', 'answer_text' => 'Latent heat is released.'],
            ['format' => 'long', 'question_text' => 'Describe the three states of matter with examples.'],
            ['format' => 'case_study', 'question_text' => 'Read the passage about a lake freezing and answer below.'],
            ['format' => 'proof', 'question_text' => 'Prove that evaporation causes cooling.'],
            ['format' => 'construction', 'question_text' => 'Draw and label the water cycle diagram.'],
        ];
        $this->fakeParser([['header' => self::HEADER, 'questions' => $questions]]);
        $job = $this->makeJob();

        $this->process($job);

        $registry = new QuestionFormatRegistry();
        $persisted = fn (string $f) => $registry->get($f)->persistedFormatCode();
        $saved = DB::table('lms_question_master')->get()->keyBy('question_format_code');
        $this->assertCount(count($questions), $saved, 'every format produced exactly one question');
        foreach ($questions as $q) {
            $row = $saved[$persisted($q['format'])];
            $this->assertSame($registry->questionTypeIdFor($registry->get($q['format'])), (int) $row->question_type_id, $q['format']);
            $this->assertSame(1, (int) $row->status, $q['format'] . ' is complete, so it is active');
            $this->assertSame($q['question_text'], $row->question_title);
        }
        // Options exist as answer rows only for the three keyed objective questions' option sets.
        $this->assertSame(3 + 2 + 3, DB::table('answer_master')->count()); // mcq 3, true/false 2, assertion_reason 3
        $this->assertSame(1, DB::table('answer_master')->where('question_id', $saved['mcq']->id)->where('correct_answer', 1)->count());
        $this->assertSame('True', DB::table('answer_master')->where('question_id', $saved['true_false']->id)->where('correct_answer', 1)->value('answer'));
        $this->assertSame('completed', $this->job($job)->status);

        $match = json_decode($saved['match_following']->answer, true);
        $this->assertSame('Ice', $match['pairs'][0]['right']);
        $this->assertSame('100', json_decode($saved['fill_blank']->answer, true)['model_answer']);
    }

    /* ---------- review and activation ---------- */

    /** A finished paper with: a keyed MCQ, an unkeyed MCQ and a short question, no chapter chosen. */
    private function paperWithoutChapter(): object
    {
        $header = ['chapter' => null] + self::HEADER;
        $this->fakeParser([['header' => $header, 'questions' => [
            $this->mcq('Which state of matter has a definite shape?', 'A'),
            $this->mcq('Which state of matter has no fixed volume?', null),
            ['item_number' => '3', 'page' => 1, 'format' => 'short', 'question_text' => 'Define evaporation.', 'answer_text' => 'Liquid to vapour.', 'marks' => 2],
        ]]]);
        $job = $this->makeJob();
        $this->process($job);

        return $this->job($job);
    }

    private function itemId(object $job, string $like): int
    {
        return (int) DB::table('question_paper_items')->where('job_id', $job->id)->where('payload', 'like', "%{$like}%")->value('id');
    }

    public function test_questions_without_a_chapter_are_saved_inactive_and_reported_in_the_breakdown(): void
    {
        $job = $this->paperWithoutChapter();

        $this->assertSame(0, DB::table('lms_question_master')->where('status', 1)->count());
        $this->assertSame(3, DB::table('lms_question_master')->where('status', 0)->count());

        $states = app(QuestionPaperJobService::class)->breakdowns([(int) $job->id])[(int) $job->id];
        $this->assertSame(
            ['saved_active' => 0, 'saved_inactive' => 3, 'unresolved' => 0, 'duplicate' => 0, 'skipped' => 0, 'pending' => 0],
            QuestionPaperJobService::shapeBreakdown($states)
        );
        $this->assertSame(3, (int) $job->questions_saved);
        $this->assertSame(3, (int) $job->questions_needing_review);
    }

    public function test_semantic_chapter_suggestion_is_ranked_but_never_assigned_without_teacher_confirmation(): void
    {
        $job = $this->paperWithoutChapter();
        Schema::table('chapter_master', fn ($table) => $table->text('chapter_desc')->nullable());
        DB::table('chapter_master')->insert(['id' => 102, 'standard_id' => 9, 'subject_id' => 3, 'chapter_name' => 'States of Matter', 'chapter_desc' => 'Matter particles in solids, liquids and gases; solids keep a definite shape and volume.', 'sub_institute_id' => self::TENANT]);
        Schema::create('semantic_intelligence', function ($table) {
            $table->increments('id'); $table->integer('chapter_id')->nullable(); $table->integer('sub_institute_id')->nullable();
            $table->text('learning_objective')->nullable(); $table->json('full_intelligence_json')->nullable();
        });
        DB::table('semantic_intelligence')->insert(['chapter_id' => 102, 'sub_institute_id' => self::TENANT, 'learning_objective' => 'Explain why solids retain a definite shape and volume.']);

        $item = collect(app(QuestionPaperReviewService::class)->items($job))->first();
        $this->assertSame(102, $item['chapter_suggestion']['chapter_id']);
        $this->assertGreaterThan(0, $item['chapter_suggestion']['confidence']);
        $this->assertNull($job->chapter_id);
        $this->assertNull(DB::table('lms_question_master')->where('id', $item['question_id'])->value('chapter_id'));
    }

    public function test_per_question_chapter_retry_updates_in_place_without_reparsing_or_duplicate_questions(): void
    {
        $parser = $this->fakeParser([['header' => ['chapter' => null] + self::HEADER, 'questions' => [$this->mcq('Which state of matter has a definite shape?', 'A')]]]);
        $job = $this->makeJob();
        $this->process($job);
        $job = $this->job($job);
        $itemId = (int) DB::table('question_paper_items')->where('job_id', $job->id)->value('id');

        $this->assertTrue(app(QuestionPaperJobService::class)->prepareManualRetry($job, ['standard_id' => 9, 'subject_id' => 3, 'item_chapters' => [$itemId => 101]]));
        $this->process($job);

        $this->assertSame(1, $parser->calls);
        $this->assertSame(1, DB::table('lms_question_master')->where('sub_institute_id', self::TENANT)->count());
        $this->assertSame(101, (int) DB::table('lms_question_master')->value('chapter_id'));
    }

    public function test_activation_is_refused_until_the_chapter_is_chosen_and_an_objective_has_its_answer(): void
    {
        $job = $this->paperWithoutChapter();
        $review = app(QuestionPaperReviewService::class);
        $keyed = $this->itemId($job, 'definite shape');

        $noChapter = $review->activate($job, [['id' => $keyed]]);
        $this->assertFalse($noChapter[0]['ok']);
        $this->assertStringContainsString('chapter', $noChapter[0]['message']);
        $this->assertSame(0, DB::table('lms_question_master')->where('status', 1)->count());

        DB::table('question_paper_jobs')->where('id', $job->id)->update(['chapter_id' => 101]);
        $job = $this->job($job);

        $unkeyed = $this->itemId($job, 'no fixed volume');
        $refused = $review->activate($job, [['id' => $unkeyed]]);
        $this->assertFalse($refused[0]['ok']);
        $this->assertStringContainsString('correct option', $refused[0]['message']);

        $notAnOption = $review->activate($job, [['id' => $unkeyed, 'correct_option' => 'Z']]);
        $this->assertFalse($notAnOption[0]['ok']);
        $this->assertSame(0, DB::table('answer_master')->where('question_id', DB::table('question_paper_items')->where('id', $unkeyed)->value('question_id'))->count());
    }

    public function test_activation_closes_exactly_the_gaps_and_updates_the_counters(): void
    {
        $job = $this->paperWithoutChapter();
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['chapter_id' => 101]);
        $job = $this->job($job);
        $review = app(QuestionPaperReviewService::class);

        $results = $review->activate($job, [
            ['id' => $this->itemId($job, 'definite shape')],
            ['id' => $this->itemId($job, 'no fixed volume'), 'correct_option' => 'c'], // reviewer picks C from the extracted options
            ['id' => $this->itemId($job, 'Define evaporation')],
        ]);

        $this->assertSame([true, true, true], array_column($results, 'ok'));
        $this->assertSame(3, DB::table('lms_question_master')->where('status', 1)->where('chapter_id', 101)->count());

        $chosen = DB::table('lms_question_master')->where('question_title', 'like', '%no fixed volume%')->first();
        $this->assertSame(4, DB::table('answer_master')->where('question_id', $chosen->id)->count());
        $this->assertSame('Gas', DB::table('answer_master')->where('question_id', $chosen->id)->where('correct_answer', 1)->value('answer'));
        $envelope = json_decode($chosen->answer, true);
        $this->assertSame('C', $envelope['correct_option']);
        $this->assertSame([], $envelope['review_reasons']);
        $this->assertSame('question_paper_upload', $envelope['source']['type'], 'provenance survives activation');

        $after = $this->job($job);
        $this->assertSame([3, 0, 0], [(int) $after->questions_saved, (int) $after->questions_needing_review, (int) $after->questions_skipped]);

        // A second activation of the same question changes nothing.
        $again = $review->activate($after, [['id' => $this->itemId($job, 'definite shape')]]);
        $this->assertFalse($again[0]['ok']);
        $this->assertSame('Already active.', $again[0]['message']);
        $this->assertSame(4 + 4, DB::table('answer_master')->count());
    }

    public function test_activation_never_touches_a_running_paper_a_foreign_row_or_a_question_this_paper_did_not_create(): void
    {
        $job = $this->paperWithoutChapter();
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['chapter_id' => 101]);
        $review = app(QuestionPaperReviewService::class);
        $short = $this->itemId($job, 'Define evaporation');

        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'processing']);
        $running = $review->activate($this->job($job), [['id' => $short]]);
        $this->assertStringContainsString('still being processed', $running[0]['message']);

        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'completed_with_warnings']);
        $job = $this->job($job);

        // The question row's provenance names a different paper: refuse.
        $qid = (int) DB::table('question_paper_items')->where('id', $short)->value('question_id');
        $envelope = json_decode(DB::table('lms_question_master')->where('id', $qid)->value('answer'), true);
        $envelope['source']['job'] = 'someone-elses-paper';
        DB::table('lms_question_master')->where('id', $qid)->update(['answer' => json_encode($envelope)]);
        $foreign = $review->activate($job, [['id' => $short]]);
        $this->assertFalse($foreign[0]['ok']);

        // An item id from another paper is not found for this one.
        $other = $this->makeJob();
        $this->assertFalse($review->activate($job, [['id' => 99999]])[0]['ok']);
        $this->assertSame(0, DB::table('lms_question_master')->where('status', 1)->count());
        $this->assertNotNull($other);
    }

    public function test_review_endpoints_preview_questions_and_are_owner_or_school_admin_only(): void
    {
        $job = $this->paperWithoutChapter();
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['chapter_id' => 101]);
        $controller = app(QuestionPaperController::class);

        $items = $controller->items($this->authed('GET'), $job->uuid)->getData(true)['data']['items'];
        $unkeyed = collect($items)->firstWhere('item_number', '1');
        $this->assertCount(3, $items);
        $this->assertSame('mcq', $items[0]['format']);
        $this->assertSame(4, count($items[0]['options']));
        $this->assertTrue(collect($items)->contains(fn ($i) => $i['needs_answer_key']));
        $this->assertTrue(collect($items)->first(fn ($i) => $i['needs_answer_key'])['can_activate'] === false);
        $this->assertNotNull($unkeyed);

        // Colleague (same school, not admin) and another school's admin both get 404 and change nothing.
        foreach ([[self::TENANT, self::COLLEAGUE, 0], [self::OTHER_TENANT, 900, 1]] as [$tenant, $user, $admin]) {
            $this->assertSame(404, $controller->items($this->authed('GET', [], $tenant, $user, $admin), $job->uuid)->getStatusCode());
            $this->assertSame(404, $controller->activate($this->authed('POST', ['items' => [['id' => 1]]], $tenant, $user, $admin), $job->uuid)->getStatusCode());
        }
        $this->assertSame(0, DB::table('lms_question_master')->where('status', 1)->count());

        $shortId = $this->itemId($job, 'Define evaporation');
        $done = $controller->activate($this->authed('POST', ['items' => [['id' => $shortId]]]), $job->uuid)->getData(true)['data'];
        $this->assertTrue($done['results'][0]['ok']);
        $this->assertSame(1, $done['job']['breakdown']['saved_active']);

        $this->assertSame(422, $controller->activate($this->authed('POST', ['items' => []]), $job->uuid)->getStatusCode());
        $this->assertSame(401, $controller->activate(tap($this->authed('POST', ['items' => [['id' => 1]]]), fn ($r) => $r->attributes->set('lms_auth', null)), $job->uuid)->getStatusCode());
    }

    public function test_the_list_and_detail_carry_a_breakdown_that_matches_the_stored_items(): void
    {
        $job = $this->paperWithoutChapter();
        $controller = app(QuestionPaperController::class);

        $detail = $controller->show($this->authed('GET'), $job->uuid)->getData(true)['data'];
        $this->assertSame(3, $detail['breakdown']['saved_inactive']);
        $this->assertSame(['id' => 9, 'name' => 'Class 9'], $detail['academic']['standard']);
        $this->assertSame(['id' => 3, 'name' => 'Science'], $detail['academic']['subject']);
        $this->assertNull($detail['academic']['chapter']);

        $list = $controller->index($this->authed('GET'))->getData(true)['data']['jobs'];
        $this->assertSame(3, $list[0]['breakdown']['saved_inactive']);
    }

    public function test_a_queued_job_reports_how_long_it_has_gone_unpicked_so_the_screen_can_say_no_worker_started_it(): void
    {
        $job = $this->makeJob();
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['updated_at' => now()->subMinutes(10)]);
        $jobs = app(QuestionPaperJobService::class);

        $waiting = $jobs->present(DB::table('question_paper_jobs')->where('id', $job->id)->first());
        $this->assertGreaterThanOrEqual(600, $waiting['queued_seconds']);

        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'processing']);
        $this->assertNull($jobs->present(DB::table('question_paper_jobs')->where('id', $job->id)->first())['queued_seconds'], 'only a queued job has a wait time');
    }

    public function test_the_extraction_job_uses_its_own_queue_and_a_retry_window_above_its_timeout(): void
    {
        $job = new \App\Jobs\QuestionPaperExtractionJob('x');

        $this->assertSame('question_papers', $job->connection);
        $this->assertSame('question-papers', $job->queue);
        $this->assertGreaterThan($job->timeout, (int) config('queue.connections.question_papers.retry_after'));
        $this->assertSame('database', config('queue.connections.question_papers.driver'));
    }
}
