<?php

namespace Tests\Feature\ExamEvaluation;

use App\Http\Middleware\RequireApiJwt;
use App\Jobs\EvaluateAnswerSheetJob;
use App\Jobs\IdentifyAnswerSheetJob;
use App\Services\Evaluation\StudentMatcherService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * The edges of the module: who is on the roll, what may be uploaded, who is
 * allowed to ask, and who gets recorded as having done it.
 */
class RosterAndUploadTest extends ExamEvaluationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
    }

    public function test_a_student_enrolled_in_two_terms_appears_once_on_the_roster(): void
    {
        $roster = (new StudentMatcherService())->roster(self::TENANT, self::SYEAR, 1, 1);

        $this->assertSame([101, 102], array_column($roster, 'student_id'));
    }

    public function test_the_roster_never_crosses_schools_or_classes(): void
    {
        DB::table('tblstudent_enrollment')->insert(['student_id' => 201, 'syear' => self::SYEAR, 'standard_id' => 1, 'grade_id' => 1, 'section_id' => 1, 'roll_no' => '1', 'sub_institute_id' => self::OTHER_TENANT, 'term_id' => 1]);
        DB::table('tblstudent_enrollment')->insert(['student_id' => 102, 'syear' => self::SYEAR, 'standard_id' => 2, 'grade_id' => 1, 'section_id' => 1, 'roll_no' => '5', 'sub_institute_id' => self::TENANT, 'term_id' => 3]);

        $roster = (new StudentMatcherService())->roster(self::TENANT, self::SYEAR, 1, 1);

        $this->assertNotContains(201, array_column($roster, 'student_id'));
    }

    public function test_creating_a_batch_is_attributed_to_the_caller_and_needs_an_exam(): void
    {
        $this->postJson('/api/exam-evaluation/batches', $this->body(['question_paper_id' => 1, 'name' => 'Class 7 Science']))
            ->assertOk()->assertJsonPath('data.batch.total_marks', 10);

        $this->assertSame(self::TEACHER, (int) DB::table('exam_evaluation_batch')->value('created_by'));

        $this->postJson('/api/exam-evaluation/batches', $this->body())->assertStatus(422)->assertJsonPath('status_code', 0);
        $this->postJson('/api/exam-evaluation/batches', $this->body(['question_paper_id' => 999]))->assertStatus(422);
    }

    public function test_a_batch_cannot_be_started_on_another_schools_paper(): void
    {
        $this->postJson('/api/exam-evaluation/batches', $this->body(['question_paper_id' => 1, 'sub_institute_id' => self::OTHER_TENANT]))->assertStatus(422);

        $this->assertSame(0, DB::table('exam_evaluation_batch')->count());
    }

    public function test_an_upload_keeps_the_original_queues_one_job_per_sheet_and_reports_the_rejects(): void
    {
        Queue::fake();
        $batchId = $this->makeBatch(['status' => 'Draft']);

        $response = $this->post("/api/exam-evaluation/batches/{$batchId}/sheets", $this->body([
            'sheets' => [
                UploadedFile::fake()->createWithContent('asha.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"),
                UploadedFile::fake()->createWithContent('notes.txt', 'plain text, not a scan'),
            ],
        ]), ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('data.uploaded', 1);
        $this->assertSame('notes.txt', $response->json('data.rejected.0.file'));

        $sheet = DB::table('exam_evaluation_sheet')->first();
        // An upload only starts the identity stage; marking waits for a teacher to confirm the student.
        $this->assertSame('Identifying', $sheet->status);
        $this->assertSame(self::TENANT, (int) $sheet->sub_institute_id);
        $this->assertSame(self::TEACHER, (int) $sheet->created_by);
        $this->assertSame('asha.pdf', $sheet->original_name);
        // Stored under a generated name, never the uploaded one, and never a student's data in the path.
        $this->assertStringNotContainsString('asha', $sheet->file_name);
        Queue::assertPushed(IdentifyAnswerSheetJob::class, 1);
        Queue::assertNotPushed(EvaluateAnswerSheetJob::class);

        @unlink(storage_path('app/exam_evaluation/' . $sheet->file_name));
    }

    public function test_an_upload_needs_a_school_and_at_least_one_file(): void
    {
        $batchId = $this->makeBatch(['status' => 'Draft']);

        $this->postJson("/api/exam-evaluation/batches/{$batchId}/sheets", $this->body())->assertStatus(422);
        $this->postJson("/api/exam-evaluation/batches/{$batchId}/sheets", ['syear' => self::SYEAR])->assertStatus(422);
    }

    public function test_nothing_can_be_added_to_another_schools_batch(): void
    {
        Bus::fake();
        $batchId = $this->makeBatch();

        $this->post("/api/exam-evaluation/batches/{$batchId}/sheets", $this->body([
            'sub_institute_id' => self::OTHER_TENANT,
            'sheets' => [UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.4')],
        ]), ['Accept' => 'application/json'])->assertStatus(404);

        $this->assertSame(0, DB::table('exam_evaluation_sheet')->count());
        Bus::assertNothingDispatched();
    }

    public function test_re_running_a_sheet_that_is_being_evaluated_is_refused(): void
    {
        $batchId = $this->makeBatch();
        $busy = $this->makeSheet($batchId, ['status' => 'Processing']);
        $approved = $this->makeSheet($batchId, ['status' => 'Approved', 'student_id' => 102]);

        $this->postJson("/api/exam-evaluation/sheets/{$busy}/reprocess", $this->body())->assertStatus(422);
        $this->postJson("/api/exam-evaluation/sheets/{$approved}/reprocess", $this->body())->assertStatus(422);
    }

    public function test_the_guard_leaves_the_verified_user_on_the_request_and_the_client_cannot_forge_it(): void
    {
        config(['api_guard.enforce' => true, 'api_guard.protect' => ['exam-evaluation/*'], 'api_guard.staff' => ['exam-evaluation/*']]);
        $guard = new RequireApiJwt();

        // A web-session caller, as the legacy Blade pages are.
        $request = Request::create('/api/exam-evaluation/batches', 'GET', ['sub_institute_id' => self::TENANT, 'verified_user_id' => 1, 'user_id' => 1]);
        $session = $this->app['session']->driver();
        $session->put(['user_id' => 4242, 'sub_institute_id' => self::TENANT, 'is_admin' => 0, 'is_student' => false, 'user_profile_name' => 'Teacher']);
        $request->setLaravelSession($session);

        $seen = null;
        $guard->handle($request, function (Request $passed) use (&$seen) {
            $seen = $passed->attributes->get('verified_user_id');

            return response('ok');
        });

        // The attribute comes from the session/JWT, not from anything in the query or body.
        $this->assertSame(4242, $seen);
    }
}
