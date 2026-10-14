<?php

namespace Tests\Feature\ExamEvaluation;

use Illuminate\Support\Facades\DB;

/**
 * Publishing: approved teacher marks reach the offline-exam tables, once.
 */
class PublishBatchTest extends ExamEvaluationTestCase
{
    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->batchId = $this->makeBatch();
    }

    /** Q1 2/2 (AI), Q2 teacher 4 (AI proposed 3), Q3 teacher 2 (AI proposed 0) -> 8 of 10. */
    private function approvedSheet(int $studentId): int
    {
        $sheetId = $this->makeSheet($this->batchId, [
            'student_id' => $studentId, 'status' => 'Approved', 'approved_by' => self::TEACHER,
            'approved_at' => now(), 'teacher_total' => 8, 'percentage' => 80,
        ]);

        foreach ([1 => 2, 2 => 4, 3 => 2] as $questionNo => $marks) {
            DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', $questionNo)->update(['teacher_marks' => $marks]);
        }

        return $sheetId;
    }

    private function publish(int $batchId, int $tenant = self::TENANT): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/exam-evaluation/batches/{$batchId}/publish", $this->body(['sub_institute_id' => $tenant]));
    }

    public function test_the_published_result_is_the_teachers_marks_for_the_right_student_and_exam(): void
    {
        $this->approvedSheet(101);

        $this->publish($this->batchId)->assertOk()->assertJsonPath('data.published', 1);

        $row = DB::table('lms_offline_exam')->first();
        $this->assertSame(101, (int) $row->student_id);
        $this->assertSame(1, (int) $row->question_paper_id);
        $this->assertSame(self::TENANT, (int) $row->sub_institute_id);
        $this->assertSame(self::SYEAR, (int) $row->syear);
        // 2 + 4 + 2: the teacher's marks, not the AI's 2 + 3 + 0.
        $this->assertSame(8, (int) $row->obtain_marks);
        $this->assertSame(self::TEACHER, (int) $row->created_by);

        $statuses = DB::table('lms_offline_exam_answer')->where('offline_exam_id', $row->id)->orderBy('question_id')->pluck('ans_status', 'question_id')->all();
        $this->assertSame([11 => 'right', 12 => 'partial', 13 => 'partial'], $statuses);

        $this->assertSame('Published', DB::table('exam_evaluation_batch')->find($this->batchId)->status);
        $this->assertNotNull(DB::table('exam_evaluation_batch')->find($this->batchId)->published_at);
    }

    public function test_publishing_twice_does_not_duplicate_the_result(): void
    {
        $this->approvedSheet(101);

        $this->publish($this->batchId)->assertOk();
        $this->publish($this->batchId)->assertStatus(422)->assertJsonPath('message', 'This batch has already been published.');

        $this->assertSame(1, DB::table('lms_offline_exam')->count());
        $this->assertSame(3, DB::table('lms_offline_exam_answer')->count());
    }

    public function test_publishing_replaces_an_earlier_result_for_the_same_student_and_paper(): void
    {
        DB::table('lms_offline_exam')->insert([
            'student_id' => 101, 'question_paper_id' => 1, 'total_right' => 0, 'total_wrong' => 3, 'obtain_marks' => 1,
            'syear' => self::SYEAR, 'sub_institute_id' => self::TENANT,
        ]);
        $this->approvedSheet(101);

        $this->publish($this->batchId)->assertOk();

        $rows = DB::table('lms_offline_exam')->where('student_id', 101)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(8, (int) $rows[0]->obtain_marks);
    }

    public function test_results_are_published_per_student_without_touching_anyone_else(): void
    {
        $this->approvedSheet(101);
        $this->approvedSheet(102);

        $this->publish($this->batchId)->assertOk()->assertJsonPath('data.published', 2);

        $this->assertEqualsCanonicalizing([101, 102], DB::table('lms_offline_exam')->pluck('student_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_sheets_that_are_not_approved_are_left_out_and_reported(): void
    {
        $this->approvedSheet(101);
        $this->makeSheet($this->batchId, ['student_id' => 102, 'status' => 'Needs review']);

        $response = $this->publish($this->batchId)->assertOk();

        $response->assertJsonPath('data.published', 1)->assertJsonPath('data.skipped', 1);
        $this->assertSame([101], DB::table('lms_offline_exam')->pluck('student_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_a_batch_with_nothing_approved_is_not_published(): void
    {
        $this->makeSheet($this->batchId, ['status' => 'Needs review']);

        $this->publish($this->batchId)->assertStatus(422);

        $this->assertSame('Review', DB::table('exam_evaluation_batch')->find($this->batchId)->status);
        $this->assertSame(0, DB::table('lms_offline_exam')->count());
    }

    public function test_a_failure_part_way_through_leaves_nothing_behind(): void
    {
        $this->approvedSheet(101);
        // The answer rows are written after the exam row, so refusing them makes
        // the publish fail half-way through.
        DB::unprepared("CREATE TRIGGER refuse_answer_rows BEFORE INSERT ON lms_offline_exam_answer BEGIN SELECT RAISE(ABORT, 'simulated failure'); END;");

        $this->publish($this->batchId)->assertStatus(500);

        $this->assertSame(0, DB::table('lms_offline_exam')->count(), 'the exam row must roll back with the failure');
        $this->assertSame('Review', DB::table('exam_evaluation_batch')->find($this->batchId)->status);
        $this->assertNull(DB::table('exam_evaluation_batch')->find($this->batchId)->published_at);
    }

    public function test_another_schools_batch_cannot_be_published(): void
    {
        $this->approvedSheet(101);

        $this->publish($this->batchId, self::OTHER_TENANT)->assertStatus(404);

        $this->assertSame(0, DB::table('lms_offline_exam')->count());
    }

    public function test_publishing_is_recorded_in_the_audit_trail(): void
    {
        $this->approvedSheet(101);

        $this->publish($this->batchId)->assertOk();

        $entry = DB::table('platform_audit_log')->where('action', 'publish')->first();
        $this->assertNotNull($entry);
        $this->assertSame('exam_evaluation_batch', $entry->entity_type);
        $this->assertSame((string) $this->batchId, $entry->entity_id);
        $this->assertSame(self::TEACHER, (int) $entry->actor_user_id);
    }

    public function test_a_published_batch_cannot_be_deleted(): void
    {
        $this->approvedSheet(101);
        $this->publish($this->batchId)->assertOk();

        $this->deleteJson("/api/exam-evaluation/batches/{$this->batchId}?sub_institute_id=" . self::TENANT)->assertStatus(422);
        $this->assertSame(1, DB::table('exam_evaluation_batch')->count());
    }
}
