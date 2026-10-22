<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\EvaluateAnswerSheetJob;
use App\Jobs\IdentifyAnswerSheetJob;
use App\Services\Evaluation\SheetIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\FakeGeminiClient;

/**
 * The teacher's half of student identification: confirming the suggested student,
 * choosing another, the checks that stop a sheet being filed under a child who is
 * not eligible, and the way the confirmed student carries through approval and
 * publication.
 *
 * Models are scripted and queues faked: nothing leaves the process.
 */
class StudentIdentificationFlowTest extends ExamEvaluationTestCase
{
    use IdentityTestHelpers;

    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->setUpScan();
        $this->batchId = $this->makeBatch(['status' => 'Review']);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->tearDownScan();
        parent::tearDown();
    }

    /** @return array<string,mixed>|null */
    private function audit(string $action): ?array
    {
        $row = DB::table('platform_audit_log')->where('action', $action)->orderByDesc('id')->first();

        return $row ? [
            'entity_id' => (int) $row->entity_id,
            'actor' => (int) $row->actor_user_id,
            'before' => json_decode((string) $row->before_json, true),
            'after' => json_decode((string) $row->after_json, true),
        ] : null;
    }

    // -- Step D: confirm the matched student ---------------------------------

    public function test_confirming_the_matched_student_records_it_keeps_how_it_was_matched_and_starts_marking(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);

        $this->review($sheetId, ['confirm_identity' => true])
            ->assertOk()
            ->assertJsonPath('data.sheet.status', 'Pending')
            ->assertJsonPath('data.sheet.identity_confirmed', true)
            ->assertJsonPath('data.sheet.identity_source', 'enrollment_no');

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertSame('enrollment_no', $sheet->identity_source, 'a confirmed AI match is still recorded as an AI match, not rewritten as manual');
        $this->assertNotNull($sheet->identity_confirmed_at);
        $this->assertSame(self::TEACHER, (int) $sheet->identity_confirmed_by);
        Queue::assertPushed(EvaluateAnswerSheetJob::class, 1);
        Queue::assertNotPushed(IdentifyAnswerSheetJob::class);

        $audit = $this->audit('confirm_identity');
        $this->assertSame($sheetId, $audit['entity_id']);
        $this->assertFalse($audit['before']['confirmed']);
        $this->assertTrue($audit['after']['confirmed']);
        $this->assertStringNotContainsString('Asha', json_encode($audit), 'the trail names ids, not children');
    }

    public function test_confirming_twice_starts_marking_once_and_keeps_the_first_confirmation(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);
        $this->review($sheetId, ['confirm_identity' => true])->assertOk();
        $firstAt = $this->sheetRow($sheetId)->identity_confirmed_at;

        $this->review($sheetId, ['confirm_identity' => true, 'user_id' => 999])->assertOk();

        Queue::assertPushed(EvaluateAnswerSheetJob::class, 1);
        $this->assertSame($firstAt, $this->sheetRow($sheetId)->identity_confirmed_at);
        $this->assertSame(self::TEACHER, (int) $this->sheetRow($sheetId)->identity_confirmed_by);
    }

    public function test_there_is_nothing_to_confirm_on_a_sheet_with_no_student(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);

        $this->review($sheetId, ['confirm_identity' => true])->assertStatus(422);

        $this->assertSame('Awaiting identity', $this->sheetRow($sheetId)->status);
        Queue::assertNothingPushed();
    }

    public function test_leaving_a_sheet_alone_leaves_it_unassigned_and_unmarked(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);

        // Nothing is sent for it: it simply stays where it is.
        $this->assertSame('Awaiting identity', $this->sheetRow($sheetId)->status);
        Queue::assertNothingPushed();
    }

    public function test_a_wrong_suggestion_can_be_cleared_without_choosing_anyone_else(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);

        $this->review($sheetId, ['student_id' => 0])->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertNull($sheet->student_id);
        $this->assertSame('unmatched', $sheet->identity_source);
        $this->assertSame('Awaiting identity', $sheet->status, 'clearing is not confirming: marking does not start');
        Queue::assertNothingPushed();
    }

    // -- Step E: choose another student --------------------------------------

    public function test_choosing_another_eligible_student_is_a_manual_assignment_and_starts_marking(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);

        $this->review($sheetId, ['student_id' => 102])->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id);
        $this->assertSame('manual', $sheet->identity_source, 'a hand-picked student is clearly distinguishable from an AI suggestion');
        $this->assertSame(100.0, (float) $sheet->identity_confidence);
        $this->assertNotNull($sheet->identity_confirmed_at);
        $this->assertSame('Pending', $sheet->status);
        Queue::assertPushed(EvaluateAnswerSheetJob::class, 1);

        $audit = $this->audit('assign_student');
        $this->assertSame(101, (int) $audit['before']['student_id']);
        $this->assertFalse($audit['before']['confirmed']);
        $this->assertSame(102, (int) $audit['after']['student_id']);
        $this->assertTrue($audit['after']['confirmed']);
    }

    public function test_the_picker_is_given_what_it_needs_to_tell_similar_students_apart(): void
    {
        $this->addSecondRaviShah();

        $roster = $this->getJson("/api/exam-evaluation/batches/{$this->batchId}?sub_institute_id=" . self::TENANT)
            ->assertOk()->json('data.roster');

        $ravis = array_values(array_filter($roster, fn ($s) => $s['name'] === 'Ravi Shah'));
        $this->assertCount(2, $ravis);
        $this->assertEqualsCanonicalizing(['GR102', 'GR103'], array_column($ravis, 'enrollment_no'));
        $this->assertEqualsCanonicalizing(['2', '3'], array_column($ravis, 'roll_no'));
        $this->assertEqualsCanonicalizing(['A', 'B'], array_column($ravis, 'section_name'));
        $this->assertSame([101, 102, 103], array_column($roster, 'student_id'));
    }

    public function test_the_sheet_list_says_what_was_read_how_it_matched_and_whether_it_is_confirmed(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId, [
            'ai_result_json' => json_encode(['student' => [], 'identity' => ['page' => 2]]),
        ]);

        $sheet = collect($this->getJson("/api/exam-evaluation/batches/{$this->batchId}?sub_institute_id=" . self::TENANT)->json('data.sheets'))
            ->firstWhere('id', $sheetId);

        $this->assertSame('scan.pdf', $sheet['original_name']);
        $this->assertSame('Awaiting identity', $sheet['status']);
        $this->assertSame(['GR101', '1', 'Asha Patel'], [$sheet['detected_enrollment_no'], $sheet['detected_roll_no'], $sheet['detected_student_name']]);
        $this->assertSame(101, $sheet['student_id']);
        $this->assertSame('enrollment_no', $sheet['identity_source']);
        $this->assertEquals(95.0, $sheet['identity_confidence']);
        $this->assertFalse($sheet['identity_confirmed']);
        $this->assertTrue($sheet['can_bulk_confirm']);
        $this->assertSame(2, $sheet['identity_page']);
    }

    // -- Step A/E: who may be assigned ---------------------------------------

    public function test_a_sheet_cannot_be_assigned_to_a_student_who_is_not_eligible_for_the_exam(): void
    {
        $this->addClassEightStudent();
        $this->addDroppedStudent();
        $sheetId = $this->awaitingSheet($this->batchId);

        foreach ([
            104 => 'class list',   // at this school, but in Class 8
            105 => 'class list',   // in Class 7 but has left
            201 => 'school',       // another school entirely
            999 => 'school',       // nobody
        ] as $studentId => $wording) {
            $response = $this->review($sheetId, ['student_id' => $studentId])->assertStatus(422);
            $this->assertStringContainsString($wording, $response->json('message'));
        }

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(101, (int) $sheet->student_id, 'a refused assignment changes nothing');
        $this->assertSame('Awaiting identity', $sheet->status);
        Queue::assertNothingPushed();
        $this->assertNull($this->audit('assign_student'));
    }

    public function test_confirming_a_suggestion_re_checks_that_the_student_is_still_eligible(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);
        DB::table('tblstudent_enrollment')->where('student_id', 101)->update(['drop_code' => 'LEFT']);

        $this->review($sheetId, ['confirm_identity' => true])->assertStatus(422);

        $this->assertNull($this->sheetRow($sheetId)->identity_confirmed_at);
        Queue::assertNothingPushed();
    }

    public function test_a_teacher_cannot_reach_another_schools_sheet_to_confirm_it(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);

        $this->review($sheetId, ['confirm_identity' => true], self::OTHER_TENANT)->assertStatus(404);

        $this->assertNull($this->sheetRow($sheetId)->identity_confirmed_at);
    }

    // -- one sheet per student -----------------------------------------------

    public function test_two_sheets_in_one_batch_cannot_both_be_assigned_the_same_student(): void
    {
        $this->awaitingSheet($this->batchId, ['student_id' => 101, 'identity_confirmed_at' => now()]);
        $second = $this->awaitingSheet($this->batchId, ['student_id' => 102]);

        $this->review($second, ['student_id' => 101])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Another sheet in this batch is already assigned to that student.');

        $this->assertSame(102, (int) $this->sheetRow($second)->student_id);
    }

    public function test_a_suggestion_that_another_sheet_already_holds_cannot_be_confirmed(): void
    {
        $this->withoutTheUniqueIndex();
        $this->awaitingSheet($this->batchId, ['student_id' => 101, 'identity_confirmed_at' => now()]);
        $second = $this->awaitingSheet($this->batchId, ['student_id' => 101]);   // the same child twice, by a race

        $this->review($second, ['confirm_identity' => true])->assertStatus(422);

        $this->assertNull($this->sheetRow($second)->identity_confirmed_at);
        Queue::assertNothingPushed();
    }

    // -- no marking before the student is known -------------------------------

    public function test_a_sheet_waiting_for_its_student_cannot_be_marked_or_approved(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);

        $this->review($sheetId, ['approve' => true])->assertStatus(422);
        $this->review($sheetId, ['marks' => [['question_no' => 1, 'teacher_marks' => 1]]])->assertStatus(422);
        $this->review($sheetId, ['unapprove' => true])->assertStatus(422);

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Awaiting identity', $sheet->status);
        $this->assertNull($sheet->teacher_total, 'no total is written for a sheet nobody has marked');
    }

    // -- Step G: corrections -------------------------------------------------

    private function markedSheet(array $over = []): int
    {
        $sheetId = $this->makeSheet($this->batchId, $over);
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 2)->update(['teacher_marks' => 4]);

        return $sheetId;
    }

    public function test_moving_a_confirmed_sheet_to_another_student_needs_a_stated_reason(): void
    {
        $sheetId = $this->markedSheet();

        $this->review($sheetId, ['student_id' => 102])->assertStatus(422);
        $this->review($sheetId, ['student_id' => 102, 'reason' => '  '])->assertStatus(422);
        $this->assertSame(101, (int) $this->sheetRow($sheetId)->student_id, 'refused: marks are not moved between children without a word');

        $this->review($sheetId, ['student_id' => 102, 'reason' => 'Scans were filed in the wrong order.'])->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id);
        $this->assertSame('manual', $sheet->identity_source);
        $this->assertSame('Needs review', $sheet->status, 'a correction does not approve anything');

        $audit = $this->audit('assign_student');
        $this->assertSame('Scans were filed in the wrong order.', $audit['after']['reason']);
        $this->assertTrue($audit['before']['confirmed']);
        $this->assertSame(101, (int) $audit['before']['student_id']);
        $this->assertSame(102, (int) $audit['after']['student_id']);
        $this->assertSame(self::TEACHER, $audit['actor']);
    }

    public function test_the_marks_stay_on_the_same_sheet_when_its_student_is_corrected(): void
    {
        $sheetId = $this->markedSheet();

        $this->review($sheetId, ['student_id' => 102, 'reason' => 'Wrong child selected.'])->assertOk();

        $answers = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->get();
        $this->assertCount(3, $answers);
        $this->assertEquals(4.0, $answers[1]->teacher_marks, 'the teacher\'s mark is still on this submission');
        $this->assertSame(0, DB::table('exam_evaluation_answer')->where('sheet_id', '!=', $sheetId)->count(), 'nothing was copied to another sheet');
    }

    public function test_an_unconfirmed_suggestion_can_be_replaced_without_a_reason(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['identity_confirmed_at' => null, 'identity_confirmed_by' => null]);

        $this->review($sheetId, ['student_id' => 102])->assertOk();

        $this->assertSame(102, (int) $this->sheetRow($sheetId)->student_id);
    }

    public function test_removing_a_confirmed_student_from_a_sheet_is_also_a_correction(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $this->review($sheetId, ['student_id' => 0])->assertStatus(422);
        $this->review($sheetId, ['student_id' => 0, 'reason' => 'Not this child.'])->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertNull($sheet->student_id);
        $this->assertNull($sheet->identity_confirmed_at, 'with nobody on the sheet there is nothing confirmed');
    }

    public function test_an_approved_sheet_must_be_reopened_before_its_student_can_be_corrected(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['status' => 'Approved']);

        $this->review($sheetId, ['student_id' => 102, 'reason' => 'Wrong child.'])->assertStatus(422);
        $this->review($sheetId, ['unapprove' => true])->assertOk();
        $this->review($sheetId, ['student_id' => 102, 'reason' => 'Wrong child.'])->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id);
        $this->assertNotSame('Approved', $sheet->status, 'it has to be approved again, by someone, after the correction');
    }

    // -- Step G: approval ---------------------------------------------------

    private function approveWith(int $sheetId, array $extra = [])
    {
        return $this->review($sheetId, $extra + [
            'approve' => true,
            'marks' => [['question_no' => 2, 'teacher_marks' => 3], ['question_no' => 3, 'teacher_marks' => 1]],
        ]);
    }

    public function test_a_sheet_whose_student_is_only_a_suggestion_cannot_be_approved(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['identity_confirmed_at' => null, 'identity_confirmed_by' => null]);

        $response = $this->approveWith($sheetId)->assertStatus(422);

        $this->assertStringContainsString('Confirm that this sheet belongs to the student', $response->json('message'));
        $this->assertNotSame('Approved', $this->sheetRow($sheetId)->status);
    }

    public function test_confirming_and_approving_can_be_done_in_one_save(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['identity_confirmed_at' => null, 'identity_confirmed_by' => null]);

        $this->approveWith($sheetId, ['confirm_identity' => true])->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Approved', $sheet->status);
        $this->assertNotNull($sheet->identity_confirmed_at);
        $this->assertSame('enrollment_no', $sheet->identity_source);
    }

    public function test_choosing_a_student_by_hand_counts_as_confirming_them(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched', 'identity_confirmed_at' => null]);

        $this->approveWith($sheetId, ['student_id' => 102])->assertOk();

        $this->assertSame(102, (int) $this->sheetRow($sheetId)->student_id);
        $this->assertSame('Approved', $this->sheetRow($sheetId)->status);
    }

    public function test_approval_checks_again_that_the_student_is_still_eligible(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        DB::table('tblstudent_enrollment')->where('student_id', 101)->update(['drop_code' => 'LEFT']);

        $response = $this->approveWith($sheetId)->assertStatus(422);

        $this->assertStringContainsString('no longer on the class list', $response->json('message'));
        $this->assertNotSame('Approved', $this->sheetRow($sheetId)->status);
    }

    public function test_approval_refuses_a_student_who_already_has_another_sheet_in_the_batch(): void
    {
        $this->withoutTheUniqueIndex();
        $this->makeSheet($this->batchId, ['student_id' => 101]);
        $second = $this->makeSheet($this->batchId, ['student_id' => 101]);

        $this->approveWith($second)->assertStatus(422);

        $this->assertNotSame('Approved', $this->sheetRow($second)->status);
    }

    // -- bulk confirmation ---------------------------------------------------

    private function bulk(array $ids, int $tenant = self::TENANT, ?int $batchId = null)
    {
        return $this->postJson(
            '/api/exam-evaluation/batches/' . ($batchId ?? $this->batchId) . '/confirm-identities',
            $this->body(['sheet_ids' => $ids, 'sub_institute_id' => $tenant])
        );
    }

    public function test_only_clean_number_matches_are_confirmed_in_bulk(): void
    {
        $byGr = $this->awaitingSheet($this->batchId, ['student_id' => 101, 'identity_source' => 'enrollment_no']);
        $byRoll = $this->awaitingSheet($this->batchId, ['student_id' => 102, 'identity_source' => 'roll_no']);
        $this->addSecondRaviShah();
        $byName = $this->awaitingSheet($this->batchId, ['student_id' => 103, 'identity_source' => 'name']);
        $unmatched = $this->awaitingSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);
        $flagged = $this->awaitingSheet($this->batchId, [
            'student_id' => null, 'identity_source' => 'unmatched',
            'ai_result_json' => json_encode(['identity' => ['issue' => 'conflict', 'candidates' => [101, 102]]]),
        ]);

        $response = $this->bulk([$byGr, $byRoll, $byName, $unmatched, $flagged])->assertOk();

        $this->assertEqualsCanonicalizing([$byGr, $byRoll], $response->json('data.confirmed'));
        $this->assertEqualsCanonicalizing(
            [$byName, $unmatched, $flagged],
            array_column($response->json('data.skipped'), 'sheet_id')
        );
        $this->assertSame(['needs_individual_review'], array_values(array_unique(array_column($response->json('data.skipped'), 'reason'))));

        $this->assertNotNull($this->sheetRow($byGr)->identity_confirmed_at);
        $this->assertSame('enrollment_no', $this->sheetRow($byGr)->identity_source);
        $this->assertSame('Pending', $this->sheetRow($byGr)->status);
        $this->assertNull($this->sheetRow($byName)->identity_confirmed_at, 'a handwritten-name match is never confirmed in bulk');
        $this->assertSame('Awaiting identity', $this->sheetRow($byName)->status);
        Queue::assertPushed(EvaluateAnswerSheetJob::class, 2);
    }

    public function test_bulk_confirmation_skips_a_sheet_whose_student_is_no_longer_eligible_or_is_claimed_twice(): void
    {
        $this->withoutTheUniqueIndex();
        $left = $this->awaitingSheet($this->batchId, ['student_id' => 101]);
        $twiceA = $this->awaitingSheet($this->batchId, ['student_id' => 102]);
        $twiceB = $this->awaitingSheet($this->batchId, ['student_id' => 102]);
        $fine = $this->awaitingSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);
        DB::table('tblstudent_enrollment')->where('student_id', 101)->update(['drop_code' => 'LEFT']);

        $response = $this->bulk([$left, $twiceA, $twiceB])->assertOk();

        // Two scans suggesting the same child: each sees the other as a claimant, so
        // NEITHER is confirmed -- which of them is really theirs is for a teacher to say.
        $skipped = collect($response->json('data.skipped'))->pluck('reason', 'sheet_id')->all();
        $this->assertSame('not_eligible', $skipped[$left]);
        $this->assertSame('duplicate', $skipped[$twiceA]);
        $this->assertSame('duplicate', $skipped[$twiceB]);
        $this->assertSame([], $response->json('data.confirmed'));
        $this->assertNull($this->sheetRow($twiceA)->identity_confirmed_at);
        $this->assertNull($this->sheetRow($twiceB)->identity_confirmed_at);
        $this->assertSame('Awaiting identity', $this->sheetRow($fine)->status);
        Queue::assertNothingPushed();
    }

    public function test_bulk_confirmation_cannot_reach_sheets_from_another_batch_or_school(): void
    {
        $otherBatch = $this->makeBatch();
        $elsewhere = $this->awaitingSheet($otherBatch, ['student_id' => 102]);

        $response = $this->bulk([$elsewhere])->assertOk();
        $this->assertSame([], $response->json('data.confirmed'));
        $this->assertSame('not_found', $response->json('data.skipped.0.reason'));
        $this->assertNull($this->sheetRow($elsewhere)->identity_confirmed_at);

        $this->bulk([$elsewhere], self::OTHER_TENANT)->assertStatus(404);
        $this->bulk([])->assertStatus(422);
        Queue::assertNothingPushed();
    }

    /**
     * Runs a request through the REAL guard configuration (not a test-only copy), so
     * this fails if the new route is ever left outside what config/api_guard.php covers.
     *
     * @param  array<string,mixed>|null  $session  null = nobody signed in
     */
    private function throughTheGuard(string $uri, ?array $session)
    {
        config(['api_guard.enforce' => true]);

        $request = \Illuminate\Http\Request::create($uri, 'POST', ['sub_institute_id' => self::TENANT, 'sheet_ids' => [1]]);

        if ($session !== null) {
            $store = $this->app['session']->driver();
            $store->put($session);
            $request->setLaravelSession($store);
        }

        return (new \App\Http\Middleware\RequireApiJwt())->handle($request, fn () => response('through'));
    }

    public function test_the_confirmation_endpoints_are_for_signed_in_teachers_of_this_school_only(): void
    {
        $teacher = ['user_id' => 4242, 'sub_institute_id' => self::TENANT, 'is_admin' => 0, 'is_student' => false, 'user_profile_name' => 'Teacher'];

        foreach (['/api/exam-evaluation/batches/1/confirm-identities', '/api/exam-evaluation/sheets/1/review'] as $uri) {
            $this->assertSame(401, $this->throughTheGuard($uri, null)->getStatusCode(), "anonymous callers are refused: {$uri}");
            $this->assertSame(403, $this->throughTheGuard($uri, ['is_student' => true, 'user_profile_name' => 'Student'] + $teacher)->getStatusCode(), "students are refused: {$uri}");
            $this->assertSame(403, $this->throughTheGuard($uri, ['user_profile_name' => 'Parent'] + $teacher)->getStatusCode(), "parents are refused: {$uri}");
            $this->assertSame(403, $this->throughTheGuard($uri, ['sub_institute_id' => self::OTHER_TENANT] + $teacher)->getStatusCode(), "another school's staff are refused: {$uri}");
            $this->assertSame('through', $this->throughTheGuard($uri, $teacher)->getContent(), "a teacher of this school gets through: {$uri}");
        }
    }

    public function test_a_published_batch_takes_no_confirmations(): void
    {
        $published = $this->makeBatch(['status' => 'Published']);
        $sheetId = $this->awaitingSheet($published);

        $this->bulk([$sheetId], self::TENANT, $published)->assertStatus(422);
    }

    // -- re-running ----------------------------------------------------------

    public function test_re_running_a_confirmed_sheet_re_marks_it_and_keeps_its_student(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/reprocess", $this->body())->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Pending', $sheet->status);
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertNotNull($sheet->identity_confirmed_at);
        Queue::assertPushed(EvaluateAnswerSheetJob::class, 1);
        Queue::assertNotPushed(IdentifyAnswerSheetJob::class);
    }

    public function test_re_running_a_sheet_nobody_has_confirmed_goes_back_to_identification_not_to_marking(): void
    {
        $evaluated = $this->makeSheet($this->batchId, ['identity_confirmed_at' => null, 'identity_confirmed_by' => null]);
        $awaiting = $this->awaitingSheet($this->batchId, ['student_id' => 102]);

        $this->postJson("/api/exam-evaluation/sheets/{$evaluated}/reprocess", $this->body())->assertOk();
        $this->postJson("/api/exam-evaluation/sheets/{$awaiting}/reprocess", $this->body())->assertOk();

        $this->assertSame('Identifying', $this->sheetRow($evaluated)->status);
        $this->assertSame('Identifying', $this->sheetRow($awaiting)->status);
        Queue::assertPushed(IdentifyAnswerSheetJob::class, 2);
        Queue::assertNotPushed(EvaluateAnswerSheetJob::class);
    }

    public function test_a_sheet_that_is_being_read_cannot_be_re_run(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/reprocess", $this->body())->assertStatus(422);

        Queue::assertNothingPushed();
    }

    // -- the batch while sheets wait for a teacher ---------------------------

    public function test_a_batch_whose_sheets_are_waiting_for_a_teacher_is_in_review_not_processing(): void
    {
        $this->awaitingSheet($this->batchId);
        $this->awaitingSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);

        EvaluateAnswerSheetJob::refreshBatch($this->batchId);

        $this->assertSame('Review', DB::table('exam_evaluation_batch')->find($this->batchId)->status);

        $this->identifyingSheet($this->batchId);
        EvaluateAnswerSheetJob::refreshBatch($this->batchId);

        $this->assertSame('Processing', DB::table('exam_evaluation_batch')->find($this->batchId)->status, 'a sheet still being read is real work in flight');
    }

    // -- Step F/G: the confirmed student carries through to the result --------

    public function test_the_student_a_teacher_confirmed_is_the_student_who_is_approved_and_published(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);

        // The teacher disagrees with the suggestion and picks Ravi.
        $this->review($sheetId, ['student_id' => 102])->assertOk();
        Queue::assertPushed(EvaluateAnswerSheetJob::class, 1);

        // The queue then runs evaluation; the scan still reads as Asha's.
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply(3)]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame(102, (int) $sheet->student_id, 'the full read must not move the sheet back to the student it names');
        $this->assertSame('manual', $sheet->identity_source);
        $this->assertCount(3, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->get());

        $this->review($sheetId, ['approve' => true, 'marks' => [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 2]]])->assertOk();
        $this->postJson("/api/exam-evaluation/batches/{$this->batchId}/publish", $this->body())->assertOk()->assertJsonPath('data.published', 1);

        $result = DB::table('lms_offline_exam')->where('question_paper_id', 1)->get();
        $this->assertCount(1, $result);
        $this->assertSame(102, (int) $result[0]->student_id, 'the published result is filed under the confirmed student');
        $this->assertSame(8, (int) $result[0]->obtain_marks);   // 2 (Q1) + 4 + 2, teacher marks
        $this->assertSame([102], DB::table('lms_offline_exam_answer')->pluck('student_id')->unique()->values()->map(fn ($id) => (int) $id)->all());
    }

    public function test_nothing_is_published_while_an_approved_sheets_student_is_no_longer_eligible(): void
    {
        $this->makeSheet($this->batchId, ['student_id' => 102, 'status' => 'Approved', 'approved_by' => self::TEACHER]);
        $this->makeSheet($this->batchId, ['student_id' => 101, 'status' => 'Approved', 'approved_by' => self::TEACHER]);
        DB::table('tblstudent_enrollment')->where('student_id', 101)->update(['drop_code' => 'LEFT']);

        $response = $this->postJson("/api/exam-evaluation/batches/{$this->batchId}/publish", $this->body())->assertStatus(422);

        $this->assertStringContainsString('no longer on the class list', $response->json('message'));
        $this->assertSame(0, DB::table('lms_offline_exam')->count(), 'not even the good sheet is published half-way');
        $this->assertSame('Review', DB::table('exam_evaluation_batch')->find($this->batchId)->status);
    }

    public function test_nothing_is_published_when_two_approved_sheets_name_the_same_student(): void
    {
        $this->withoutTheUniqueIndex();
        $this->makeSheet($this->batchId, ['student_id' => 101, 'status' => 'Approved']);
        $this->makeSheet($this->batchId, ['student_id' => 101, 'status' => 'Approved']);

        $this->postJson("/api/exam-evaluation/batches/{$this->batchId}/publish", $this->body())->assertStatus(422);

        $this->assertSame(0, DB::table('lms_offline_exam')->count());
    }

    // -- before the migration is applied -------------------------------------

    public function test_confirmation_still_holds_before_the_confirmation_columns_exist(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);

        // The shared database has not had the migration applied yet.
        Schema::table('exam_evaluation_sheet', fn ($table) => $table->dropColumn(['identity_confirmed_at', 'identity_confirmed_by']));
        SheetIdentity::forgetSchema();

        $this->review($sheetId, ['confirm_identity' => true])->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('manual', $sheet->identity_source, 'the nearest durable marker every job already honours');
        $this->assertSame('Pending', $sheet->status);
        $this->assertTrue(SheetIdentity::isConfirmed($sheet));

        // And a later read still cannot undo it.
        $this->evaluate($sheetId, new FakeGeminiClient([$this->readerReply(['enrollment_no' => 'GR102', 'roll_no' => '2', 'name' => 'Ravi Shah']), $this->graderReply()]));

        $this->assertSame(101, (int) $this->sheetRow($sheetId)->student_id);
        SheetIdentity::forgetSchema();
    }

    public function test_the_migration_adds_and_removes_only_the_two_confirmation_columns(): void
    {
        $migration = require base_path('database/migrations/2026_10_13_110000_add_identity_confirmation_to_exam_evaluation_sheet.php');

        $this->assertTrue(Schema::hasColumns('exam_evaluation_sheet', ['identity_confirmed_at', 'identity_confirmed_by']));

        $migration->up();   // running it again is harmless
        $this->assertTrue(Schema::hasColumns('exam_evaluation_sheet', ['identity_confirmed_at', 'identity_confirmed_by']));

        $before = Schema::getColumnListing('exam_evaluation_sheet');
        $migration->down();
        $after = Schema::getColumnListing('exam_evaluation_sheet');

        $this->assertEqualsCanonicalizing(['identity_confirmed_at', 'identity_confirmed_by'], array_values(array_diff($before, $after)));
        $this->assertFalse(Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_at'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_by'));
    }
}
