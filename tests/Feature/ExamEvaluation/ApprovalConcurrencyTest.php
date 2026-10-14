<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\EvaluateAnswerSheetJob;
use App\Jobs\IdentifyAnswerSheetJob;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Approval, re-opening and re-running when two requests meet.
 *
 * WHAT THIS CAN AND CANNOT SHOW. SQLite has no row locks (`lockForUpdate()` compiles to
 * nothing) and one connection, so two requests cannot truly run at once here. What it CAN do
 * is stage the interleaving that was the bug: a competing request COMMITS between the
 * controller's first read of the sheet and the transaction that acts on it. Under the old
 * shape -- validate on the copy just read, lock only to write -- the second request then
 * acted on a state that was no longer true. Now every rule is judged on the rows read after
 * the lock, so these tests fail on that old shape and pass on this one.
 *
 * Real simultaneous requests against a real lock manager are exercised by
 * ReviewConcurrencyMysqlTest, which only runs against an isolated MySQL/MariaDB you point it
 * at (see its header). Nothing in this file touches a shared database.
 */
class ApprovalConcurrencyTest extends ExamEvaluationTestCase
{
    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->batchId = $this->makeBatch(['status' => 'Review']);
        Queue::fake();
    }

    /** @return array<string,mixed> */
    private function approveBody(array $extra = []): array
    {
        return $extra + [
            'approve' => true,
            'marks' => [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 2]],
        ];
    }

    private function sheetRow(int $id): object
    {
        return DB::table('exam_evaluation_sheet')->find($id);
    }

    /** @return array<int,string> */
    private function auditActions(int $sheetId): array
    {
        return DB::table('platform_audit_log')
            ->where('entity_type', 'exam_evaluation_sheet')->where('entity_id', (string) $sheetId)
            ->orderBy('id')->pluck('action')->all();
    }

    /**
     * Runs $competing once, the moment after the controller has first read the sheet
     * and before it opens the transaction that decides what to do -- the window a second
     * request used to slip through.
     */
    private function afterTheFirstRead(\Closure $competing): void
    {
        $fired = false;

        DB::listen(function (QueryExecuted $query) use (&$fired, $competing) {
            if ($fired || ! str_contains($query->sql, 'from "exam_evaluation_sheet"') || ! str_starts_with($query->sql, 'select')) {
                return;
            }

            $fired = true;
            $competing();
        });
    }

    /** Another request's approval, as it would have committed. */
    private function approveBehindTheirBack(int $sheetId): void
    {
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 2)->update(['teacher_marks' => 5]);
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 3)->update(['teacher_marks' => 3]);
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 1)->update(['teacher_marks' => 2]);
        DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update([
            'status' => 'Approved', 'approved_by' => 111, 'approved_at' => '2026-10-13 09:00:00', 'teacher_total' => 10, 'percentage' => 100,
        ]);
    }

    // -- two approvals --------------------------------------------------------

    public function test_a_second_approval_after_the_first_changes_nothing_and_is_audited_once(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $this->review($sheetId, $this->approveBody())->assertOk();
        $first = $this->sheetRow($sheetId);

        // Someone else clicks Approve a moment later (or in another tab). A bare approval of an
        // approved sheet is a no-op; one that carries marks is an edit, and is refused (next test).
        $this->review($sheetId, ['approve' => true, 'user_id' => 999])->assertOk();
        $this->review($sheetId, $this->approveBody(['user_id' => 999]))->assertStatus(422);
        $second = $this->sheetRow($sheetId);

        $this->assertSame(self::TEACHER, (int) $second->approved_by, 'the approver is whoever approved it first');
        $this->assertSame($first->approved_at, $second->approved_at, 'the approval time is not re-stamped');
        $this->assertSame(['approve'], array_values(array_filter($this->auditActions($sheetId), fn ($a) => $a === 'approve')), 'one approval, one audit event');

        $audit = DB::table('platform_audit_log')->where('action', 'approve')->get();
        $this->assertCount(1, $audit);
        $this->assertSame(self::TEACHER, (int) $audit[0]->actor_user_id, 'and the audit actor is the same person the sheet names');
    }

    public function test_an_approval_that_commits_before_ours_acts_is_not_restamped_or_audited_again(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->afterTheFirstRead(fn () => $this->approveBehindTheirBack($sheetId));

        // Our request read the sheet as "Needs review"; by the time it holds the lock it is Approved.
        // (A bare approval: with marks attached it would be an edit, and is refused instead.)
        $this->review($sheetId, ['approve' => true])->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Approved', $sheet->status);
        $this->assertSame(111, (int) $sheet->approved_by, 'the first approver keeps the approval');
        $this->assertSame('2026-10-13 09:00:00', (string) $sheet->approved_at, 'with the first approval\'s own timestamp');
        $this->assertSame([], array_filter($this->auditActions($sheetId), fn ($a) => $a === 'approve'), 'this request changed nothing, so it records nothing');
    }

    // -- an edit that meets an approval ----------------------------------------

    public function test_an_edit_sent_after_an_approval_committed_is_refused_and_leaves_the_marks_alone(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->review($sheetId, $this->approveBody())->assertOk();
        $approvedTotal = $this->sheetRow($sheetId)->teacher_total;

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 1]]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This sheet is approved. Re-open it before changing its marks or student.');

        $this->assertEquals(4.0, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 2)->value('teacher_marks'));
        $this->assertEquals($approvedTotal, $this->sheetRow($sheetId)->teacher_total);
    }

    public function test_an_edit_that_loses_the_race_to_an_approval_cannot_land_on_the_approved_sheet(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        // The edit was sent while the sheet was open; another request approves it before the edit is applied.
        $this->afterTheFirstRead(fn () => $this->approveBehindTheirBack($sheetId));

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 1], ['question_no' => 3, 'teacher_marks' => 0]]])
            ->assertStatus(422);

        $answers = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->pluck('teacher_marks', 'question_no')->all();
        $this->assertEquals([1 => 2.0, 2 => 5.0, 3 => 3.0], array_map('floatval', $answers), 'the approved marks stand; the late edit wrote nothing');
        $this->assertEquals(10.0, $this->sheetRow($sheetId)->teacher_total);
        $this->assertSame([], array_filter($this->auditActions($sheetId), fn ($a) => $a === 'update_marks'), 'and is not in the trail as if it had happened');
    }

    public function test_a_student_change_that_loses_the_race_to_an_approval_is_refused_too(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->afterTheFirstRead(fn () => $this->approveBehindTheirBack($sheetId));

        $this->review($sheetId, ['student_id' => 102, 'reason' => 'Wrong child.'])->assertStatus(422);

        $this->assertSame(101, (int) $this->sheetRow($sheetId)->student_id);
    }

    // -- re-opening ------------------------------------------------------------

    /** @return array<string,array{0:string}> */
    public static function statesThatAreNotApproved(): array
    {
        return [
            'pending' => ['Pending'],
            'processing' => ['Processing'],
            'failed' => ['Failed'],
            'identifying' => ['Identifying'],
            'awaiting identity' => ['Awaiting identity'],
            'needs review' => ['Needs review'],
            'evaluated' => ['Evaluated'],
        ];
    }

    #[DataProvider('statesThatAreNotApproved')]
    public function test_only_an_approved_sheet_can_be_reopened(string $status): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['status' => $status]);
        $before = (array) $this->sheetRow($sheetId);

        $this->review($sheetId, ['unapprove' => true])
            ->assertStatus(422)
            ->assertJsonPath('message', "Only an approved sheet can be re-opened. This one is \"{$status}\".");

        $this->assertEquals($before, (array) $this->sheetRow($sheetId), 'nothing about the sheet changed');
        $this->assertSame([], $this->auditActions($sheetId), 'and nothing is claimed in the trail');
    }

    public function test_a_failed_sheet_is_not_turned_into_needs_review_by_a_reopen(): void
    {
        // The case that mattered: a sheet that failed has no marks, and "Needs review" would pass it off as marked work.
        $sheetId = $this->makeSheet($this->batchId, ['status' => 'Failed', 'failure_reason' => 'The scan could not be read.']);

        $this->review($sheetId, ['unapprove' => true])->assertStatus(422);

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Failed', $sheet->status);
        $this->assertSame('The scan could not be read.', $sheet->failure_reason);
    }

    public function test_reopening_an_approved_sheet_works_clears_the_approval_and_is_audited_once(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->review($sheetId, $this->approveBody())->assertOk();

        $this->review($sheetId, ['unapprove' => true])->assertOk()->assertJsonPath('data.sheet.status', 'Needs review');

        $sheet = $this->sheetRow($sheetId);
        $this->assertNull($sheet->approved_by);
        $this->assertNull($sheet->approved_at);
        $this->assertSame(['approve', 'reopen'], array_values(array_filter($this->auditActions($sheetId), fn ($a) => in_array($a, ['approve', 'reopen'], true))));

        $reopen = DB::table('platform_audit_log')->where('action', 'reopen')->first();
        $this->assertSame('Approved', json_decode($reopen->before_json, true)['status']);
        $this->assertSame('Needs review', json_decode($reopen->after_json, true)['status']);
    }

    public function test_a_second_reopen_is_refused_and_not_audited(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->review($sheetId, $this->approveBody())->assertOk();
        $this->review($sheetId, ['unapprove' => true])->assertOk();

        $this->review($sheetId, ['unapprove' => true])->assertStatus(422);

        $this->assertSame(1, DB::table('platform_audit_log')->where('action', 'reopen')->count());
    }

    public function test_a_reopen_that_loses_to_another_reopen_in_flight_is_refused_and_not_audited(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['status' => 'Approved', 'approved_by' => self::TEACHER, 'approved_at' => now()]);
        // Our click read an approved sheet; a colleague's re-open commits before ours is applied.
        $this->afterTheFirstRead(fn () => DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['status' => 'Needs review', 'approved_by' => null, 'approved_at' => null]));

        $this->review($sheetId, ['unapprove' => true])->assertStatus(422);

        $this->assertSame(0, DB::table('platform_audit_log')->where('action', 'reopen')->count(), 'only the request that changed the state records it');
    }

    public function test_re_approving_after_a_reopen_stamps_a_fresh_approval(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->review($sheetId, $this->approveBody())->assertOk();
        $this->review($sheetId, ['unapprove' => true])->assertOk();

        $this->review($sheetId, ['approve' => true, 'user_id' => 777])->assertOk();

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Approved', $sheet->status);
        $this->assertSame(777, (int) $sheet->approved_by);
        $this->assertSame(['approve', 'reopen', 'approve'], array_values(array_filter($this->auditActions($sheetId), fn ($a) => in_array($a, ['approve', 'reopen'], true))));
    }

    public function test_a_published_batch_cannot_have_a_sheet_reopened(): void
    {
        $published = $this->makeBatch(['status' => 'Published']);
        $sheetId = $this->makeSheet($published, ['status' => 'Approved', 'approved_by' => self::TEACHER, 'approved_at' => now()]);

        $this->review($sheetId, ['unapprove' => true])->assertStatus(422);

        $this->assertSame('Approved', $this->sheetRow($sheetId)->status);
    }

    // -- re-running meets an approval ------------------------------------------

    public function test_a_rerun_cannot_reset_a_sheet_that_was_approved_while_the_click_was_in_flight(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        // The re-run click read an open sheet; an approval commits before the re-run is applied.
        $this->afterTheFirstRead(fn () => $this->approveBehindTheirBack($sheetId));

        $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/reprocess", $this->body())->assertStatus(422);

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Approved', $sheet->status, 'an approved sheet is not reset to Pending by a stale re-run');
        $this->assertSame(111, (int) $sheet->approved_by);
        Queue::assertNothingPushed();
        $this->assertSame([], array_filter($this->auditActions($sheetId), fn ($a) => $a === 'reprocess'));
    }

    public function test_a_rerun_in_a_published_batch_is_refused(): void
    {
        $published = $this->makeBatch(['status' => 'Published']);
        $sheetId = $this->makeSheet($published);

        $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/reprocess", $this->body())->assertStatus(422);

        $this->assertSame('Needs review', $this->sheetRow($sheetId)->status);
        Queue::assertNothingPushed();
    }

    public function test_a_rerun_is_audited_with_where_it_came_from_and_where_it_went(): void
    {
        $confirmed = $this->makeSheet($this->batchId);
        $unconfirmed = $this->makeSheet($this->batchId, ['student_id' => 102, 'identity_confirmed_at' => null, 'identity_confirmed_by' => null]);

        $this->postJson("/api/exam-evaluation/sheets/{$confirmed}/reprocess", $this->body())->assertOk();
        $this->postJson("/api/exam-evaluation/sheets/{$unconfirmed}/reprocess", $this->body())->assertOk();

        $rows = DB::table('platform_audit_log')->where('action', 'reprocess')->orderBy('id')->get();
        $this->assertSame(['Needs review', 'Needs review'], $rows->map(fn ($r) => json_decode($r->before_json, true)['status'])->all());
        $this->assertSame(['Pending', 'Identifying'], $rows->map(fn ($r) => json_decode($r->after_json, true)['status'])->all());
        Queue::assertPushed(EvaluateAnswerSheetJob::class, 1);
        Queue::assertPushed(IdentifyAnswerSheetJob::class, 1);
    }

    // -- removing things leaves a trail, and not from a published batch ---------

    public function test_removing_a_sheet_is_audited_with_ids_only(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['original_name' => 'asha_patel_science.pdf']);

        $this->deleteJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT)->assertOk();

        $audit = DB::table('platform_audit_log')->where('action', 'delete_sheet')->first();
        $this->assertNotNull($audit);
        $this->assertSame((string) $sheetId, $audit->entity_id);
        $before = json_decode($audit->before_json, true);
        $this->assertSame(['status' => 'Needs review', 'student_id' => 101, 'batch_id' => $this->batchId], $before);
        $this->assertStringNotContainsString('asha', strtolower($audit->before_json . $audit->after_json), 'the scan\'s name can carry a child\'s name; the trail does not');
        $this->assertSame(0, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count());
    }

    public function test_a_sheet_in_a_published_batch_cannot_be_removed(): void
    {
        $published = $this->makeBatch(['status' => 'Published']);
        $sheetId = $this->makeSheet($published, ['status' => 'Approved']);

        $this->deleteJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT)->assertStatus(422);

        $this->assertNotNull($this->sheetRow($sheetId));
        $this->assertSame(3, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->count());
        $this->assertNull(DB::table('platform_audit_log')->where('action', 'delete_sheet')->first());
    }

    public function test_removing_a_batch_is_audited_and_a_published_one_is_refused(): void
    {
        $open = $this->makeBatch(['status' => 'Review']);
        $this->makeSheet($open);
        $published = $this->makeBatch(['status' => 'Published']);

        $this->deleteJson("/api/exam-evaluation/batches/{$published}?sub_institute_id=" . self::TENANT)->assertStatus(422);
        $this->deleteJson("/api/exam-evaluation/batches/{$open}?sub_institute_id=" . self::TENANT)->assertOk();

        $audit = DB::table('platform_audit_log')->where('action', 'delete_batch')->get();
        $this->assertCount(1, $audit, 'only the removal that happened');
        $this->assertSame((string) $open, $audit[0]->entity_id);
        $this->assertSame(['status' => 'Review', 'sheets' => 1], json_decode($audit[0]->before_json, true));
        $this->assertNotNull(DB::table('exam_evaluation_batch')->find($published));
    }

    // -- a refused request writes nothing --------------------------------------

    public function test_a_review_refused_part_way_writes_none_of_it(): void
    {
        $sheetId = $this->makeSheet($this->batchId);
        $this->addClassEightPupil();

        // A valid mark, a valid note, and a student who is not eligible: all or nothing.
        $this->review($sheetId, [
            'marks' => [['question_no' => 2, 'teacher_marks' => 4, 'teacher_feedback' => 'Good.']],
            'student_id' => 104,
            'reason' => 'x of it',
        ])->assertStatus(422);

        $answer = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 2)->first();
        $this->assertNull($answer->teacher_marks);
        $this->assertNull($answer->teacher_feedback);
        $this->assertSame(101, (int) $this->sheetRow($sheetId)->student_id);
        $this->assertSame([], $this->auditActions($sheetId));
    }

    private function addClassEightPupil(): void
    {
        DB::table('tblstudent')->insert(['id' => 104, 'first_name' => 'Neha', 'last_name' => 'Desai', 'enrollment_no' => 'GR104', 'sub_institute_id' => self::TENANT]);
        DB::table('tblstudent_enrollment')->insert(['student_id' => 104, 'syear' => self::SYEAR, 'standard_id' => 2, 'grade_id' => 1, 'section_id' => 1, 'roll_no' => '1', 'sub_institute_id' => self::TENANT, 'term_id' => 1]);
    }
}
