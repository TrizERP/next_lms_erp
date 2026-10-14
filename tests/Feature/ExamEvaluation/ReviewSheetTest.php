<?php

namespace Tests\Feature\ExamEvaluation;

use Illuminate\Support\Facades\DB;

/**
 * A teacher's pass over one sheet: edit, save a draft, come back, approve.
 */
class ReviewSheetTest extends ExamEvaluationTestCase
{
    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->batchId = $this->makeBatch();
    }

    private function answer(int $sheetId, int $questionNo): object
    {
        return DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', $questionNo)->first();
    }

    public function test_teacher_edit_changes_the_total_and_the_percentage(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        // AI proposed 3/5 on Q2; the teacher gives 4.
        $response = $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4]]]);

        $response->assertOk()->assertJsonPath('status_code', 1);
        $this->assertEquals(4.0, $this->answer($sheetId, 2)->teacher_marks);
        // The AI's number is untouched: the two stay distinct.
        $this->assertEquals(3.0, $this->answer($sheetId, 2)->ai_marks);

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        // 2 (Q1, AI) + 4 (Q2, teacher) + 0 (Q3, AI) = 6 of 10
        $this->assertEquals(6.0, $sheet->teacher_total);
        $this->assertEquals(60.0, $sheet->percentage);
        $response->assertJsonPath('data.sheet.teacher_total', 6)->assertJsonPath('data.sheet.percentage', 60);
    }

    public function test_marks_above_the_maximum_are_refused_not_clamped(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $response = $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 6]]]);

        $response->assertStatus(422)->assertJsonPath('status_code', 0);
        $this->assertStringContainsString('Question 2: marks must be between 0 and 5', $response->json('message'));
        $this->assertNull($this->answer($sheetId, 2)->teacher_marks, 'nothing may be written when any mark is invalid');
    }

    public function test_one_bad_mark_stops_the_whole_save(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $this->review($sheetId, ['marks' => [
            ['question_no' => 1, 'teacher_marks' => 1],
            ['question_no' => 2, 'teacher_marks' => 99],
        ]])->assertStatus(422);

        $this->assertNull($this->answer($sheetId, 1)->teacher_marks);
    }

    public function test_negative_marks_are_refused(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => -1]]])->assertStatus(422);
    }

    public function test_fractional_marks_are_kept_to_two_places(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 3.5]]])->assertOk();

        $this->assertEquals(3.5, $this->answer($sheetId, 2)->teacher_marks);
    }

    public function test_draft_saves_marks_and_feedback_and_survives_a_reload(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4, 'teacher_feedback' => 'Missed the role of light.']]])->assertOk();

        $reloaded = $this->getJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT);
        $reloaded->assertOk();

        $q2 = collect($reloaded->json('data.answers'))->firstWhere('question_no', 2);
        $this->assertSame(4, (int) $q2['teacher_marks']);
        $this->assertSame('Missed the role of light.', $q2['teacher_feedback']);
        $this->assertTrue($q2['reviewed']);
        $this->assertSame(3, (int) $q2['ai_marks'], 'the AI suggestion stays visible beside the teacher mark');
        $this->assertNotSame('Approved', $reloaded->json('data.sheet.status'), 'a draft is not an approval');
    }

    public function test_feedback_only_edit_does_not_clear_the_marks(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4]]])->assertOk();

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_feedback' => 'Good attempt.']]])->assertOk();

        $this->assertEquals(4.0, $this->answer($sheetId, 2)->teacher_marks);
        $this->assertSame('Good attempt.', $this->answer($sheetId, 2)->teacher_feedback);
    }

    public function test_clearing_a_mark_returns_the_question_to_the_ai_suggestion(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4]]])->assertOk();

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => null, 'teacher_feedback' => null]]])->assertOk();

        $this->assertNull($this->answer($sheetId, 2)->teacher_marks);
        $this->assertNull($this->answer($sheetId, 2)->teacher_feedback);
        $this->assertEquals(5.0, DB::table('exam_evaluation_sheet')->find($sheetId)->teacher_total);
    }

    public function test_cannot_approve_while_a_flagged_question_has_no_teacher_mark(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $response = $this->review($sheetId, ['approve' => true]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Questions 2, 3 need your mark', $response->json('message'));
        $this->assertSame('Needs review', DB::table('exam_evaluation_sheet')->find($sheetId)->status);
    }

    public function test_approving_after_resolving_every_flagged_question(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $response = $this->review($sheetId, [
            'approve' => true,
            'marks' => [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 2]],
        ]);

        $response->assertOk();
        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame('Approved', $sheet->status);
        $this->assertSame(self::TEACHER, (int) $sheet->approved_by);
        $this->assertNotNull($sheet->approved_at);
        // The objective answer took the AI's number at approval; the rest are the teacher's.
        $this->assertEquals(2.0, $this->answer($sheetId, 1)->teacher_marks);
        $this->assertEquals(8.0, $sheet->teacher_total);
        $this->assertEquals(80.0, $sheet->percentage);
        $this->assertSame(1, DB::table('exam_evaluation_batch')->where('id', $this->batchId)->value('approved_sheets'));
    }

    public function test_accepting_the_ai_mark_counts_as_resolving_a_flagged_question(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        // "Accept AI mark" sends the AI's own number as the teacher mark.
        $this->review($sheetId, [
            'approve' => true,
            'marks' => [['question_no' => 2, 'teacher_marks' => 3], ['question_no' => 3, 'teacher_marks' => 0]],
        ])->assertOk();

        $this->assertSame('Approved', DB::table('exam_evaluation_sheet')->find($sheetId)->status);
    }

    public function test_approval_needs_a_student(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);

        $response = $this->review($sheetId, ['approve' => true, 'marks' => [['question_no' => 2, 'teacher_marks' => 3], ['question_no' => 3, 'teacher_marks' => 0]]]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Assign this sheet to a student', $response->json('message'));
    }

    public function test_approving_twice_does_not_restamp_the_approval(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $marks = [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 2]];
        $this->review($sheetId, ['approve' => true, 'marks' => $marks])->assertOk();
        DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['approved_by' => 999]);

        $this->review($sheetId, ['approve' => true])->assertOk();

        $this->assertSame(999, (int) DB::table('exam_evaluation_sheet')->find($sheetId)->approved_by);
    }

    public function test_an_approved_sheet_refuses_edits_until_it_is_reopened(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->review($sheetId, ['approve' => true, 'marks' => [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 2]]])->assertOk();

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 1]]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This sheet is approved. Re-open it before changing its marks or student.');

        $this->review($sheetId, ['unapprove' => true])->assertOk();
        $this->assertSame('Needs review', DB::table('exam_evaluation_sheet')->find($sheetId)->status);
        $this->assertNull(DB::table('exam_evaluation_sheet')->find($sheetId)->approved_by);

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 1]]])->assertOk();
        $this->assertEquals(1.0, $this->answer($sheetId, 2)->teacher_marks);
    }

    public function test_a_sheet_that_is_still_processing_cannot_be_approved_or_marked(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['status' => 'Processing']);

        $this->review($sheetId, ['approve' => true])->assertStatus(422);
        $this->review($sheetId, ['marks' => [['question_no' => 1, 'teacher_marks' => 1]]])->assertStatus(422);
    }

    public function test_a_published_batch_is_closed_to_review(): void
    {
        $published = $this->makeBatch(['status' => 'Published']);
        $sheetId = $this->makeSheet($published);

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 1]]])->assertStatus(422);
    }

    public function test_another_schools_sheet_is_not_found(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4]]], self::OTHER_TENANT)->assertStatus(404);
        $this->getJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::OTHER_TENANT)->assertStatus(404);
        $this->getJson("/api/exam-evaluation/sheets/{$sheetId}/file?sub_institute_id=" . self::OTHER_TENANT)->assertStatus(404);
        $this->assertNull($this->answer($sheetId, 2)->teacher_marks);
    }

    public function test_a_student_from_another_school_cannot_be_assigned(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);

        $this->review($sheetId, ['student_id' => 201])->assertStatus(422);
    }

    public function test_two_sheets_in_one_batch_cannot_share_a_student(): void
    {
        $this->makeSheet($this->batchId, ['student_id' => 101]);
        $second = $this->makeSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);

        $this->review($second, ['student_id' => 101])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Another sheet in this batch is already assigned to that student.');
    }

    public function test_assigning_a_student_by_hand_is_recorded_as_manual(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);

        $this->review($sheetId, ['student_id' => 102])->assertOk();

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame(102, (int) $sheet->student_id);
        $this->assertSame('manual', $sheet->identity_source);
    }

    public function test_sending_the_current_student_back_changes_nothing(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confidence' => 95]);

        $this->review($sheetId, ['student_id' => 101])->assertOk();

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame('enrollment_no', $sheet->identity_source, 'how the sheet was matched must not be rewritten as "manual"');
        $this->assertEquals(95.0, $sheet->identity_confidence);
        $this->assertSame([], DB::table('platform_audit_log')->where('action', 'assign_student')->get()->all());
    }

    public function test_an_approved_sheet_still_accepts_its_own_student_being_sent_back(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['student_id' => 101, 'status' => 'Approved']);

        $this->review($sheetId, ['student_id' => 101])->assertOk();
        $this->review($sheetId, ['student_id' => 102])->assertStatus(422);
    }

    public function test_the_approver_is_the_verified_user_not_the_one_the_client_claims(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $request = \Illuminate\Http\Request::create('/x', 'POST', $this->body([
            'approve' => true,
            'user_id' => 1, // a client claiming to be somebody else
            'marks' => [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 2]],
        ]));
        // What RequireApiJwt leaves on a request that passed the guard.
        $request->attributes->set('verified_user_id', 4242);

        $formRequest = \App\Http\Requests\ExamEvaluation\ReviewSheetRequest::createFrom($request);
        $formRequest->setContainer($this->app)->setRedirector($this->app->make('redirect'));
        $formRequest->validateResolved();

        $this->app->make(\App\Http\Controllers\api\ExamEvaluationApiController::class)->review($formRequest, $sheetId);

        $this->assertSame(4242, (int) DB::table('exam_evaluation_sheet')->find($sheetId)->approved_by);
    }

    public function test_every_change_is_written_to_the_audit_trail(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4]]])->assertOk();
        $this->review($sheetId, ['approve' => true, 'marks' => [['question_no' => 3, 'teacher_marks' => 2]]])->assertOk();
        $this->review($sheetId, ['unapprove' => true])->assertOk();

        $actions = DB::table('platform_audit_log')->where('module', 'exam_evaluation')->orderBy('id')->pluck('action')->all();

        $this->assertSame(['update_marks', 'update_marks', 'approve', 'reopen'], $actions);

        $first = DB::table('platform_audit_log')->where('action', 'update_marks')->orderBy('id')->first();
        $this->assertSame('exam_evaluation_sheet', $first->entity_type);
        $this->assertSame((string) $sheetId, $first->entity_id);
        $this->assertSame(self::TEACHER, (int) $first->actor_user_id);
        $this->assertSame(['q2' => ['teacher_marks' => null]], json_decode($first->before_json, true));
        $this->assertEquals(['q2' => ['teacher_marks' => 4]], json_decode($first->after_json, true));
    }

    public function test_the_review_payload_carries_the_header_context(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $response = $this->getJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT)->assertOk();

        $context = $response->json('data.context');
        $this->assertSame('Class 7 Science', $context['batch_name']);
        $this->assertSame('Term 1 Science', $context['paper_name']);
        $this->assertSame('Science', $context['subject_name']);
        $this->assertSame('Class 7', $context['class_name']);
        $this->assertSame('A', $context['section_name']);
        $this->assertSame(self::SYEAR, $context['syear']);

        $flags = collect($response->json('data.answers'))->pluck('needs_attention', 'question_no')->all();
        $this->assertSame([1 => false, 2 => true, 3 => true], $flags);
    }
}
