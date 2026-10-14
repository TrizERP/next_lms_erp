<?php

namespace Tests\Feature\ExamEvaluation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * The JSON the exam-evaluation screens read, pinned key by key.
 *
 * The frontend (lms_k12, app/lms/exam/_exam-evaluation/types.ts) is a separate repository, so a
 * renamed or dropped key here breaks it silently at runtime. These lists are the contract; the
 * frontend keeps a copy in lib/exam-evaluation/contract.test.ts and checks its own types against
 * it. A change to either side has to change the list, and so shows up in review on both.
 *
 * Add a key: add it here AND there. Rename or remove one: that is a breaking change.
 */
class ApiContractTest extends ExamEvaluationTestCase
{
    use IdentityTestHelpers;

    /** @var array<int,string> */
    public const SHEET = [
        'id', 'batch_id', 'original_name', 'file_type', 'status',
        'student_id', 'student_name', 'student_roll_no', 'student_enrollment_no',
        'detected_roll_no', 'detected_enrollment_no', 'detected_student_name',
        'identity_source', 'identity_confidence', 'identity_confirmed', 'identity_confirmed_at',
        'identity_issue', 'identity_candidates', 'identity_page', 'duplicate_of_sheet_id',
        'can_bulk_confirm', 'stalled',
        'ai_total', 'teacher_total', 'max_marks', 'percentage', 'has_annotated',
        'failure_reason', 'evaluated_at', 'approved_at',
    ];

    /** @var array<int,string> */
    public const BATCH = [
        'id', 'question_paper_id', 'name', 'status', 'syear', 'total_sheets', 'evaluated_sheets',
        'approved_sheets', 'total_marks', 'paper_name', 'exam_type', 'standard_name', 'subject_name',
        'published_at', 'created_at',
    ];

    /** @var array<int,string> */
    public const ROSTER_STUDENT = ['student_id', 'roll_no', 'enrollment_no', 'name', 'section_name'];

    /** @var array<int,string> */
    public const CONTEXT = [
        'batch_id', 'batch_name', 'batch_status', 'paper_id', 'paper_name', 'exam_type', 'subject_name',
        'class_name', 'grade_name', 'section_name', 'syear', 'total_marks',
    ];

    /** @var array<int,string> */
    public const ANSWER = [
        'id', 'question_no', 'question_id', 'question_title', 'question_type', 'is_objective', 'detected_answer',
        'selected_options', 'expected_answer', 'max_marks', 'ai_marks', 'teacher_marks', 'status', 'ai_confidence',
        'ai_remark', 'teacher_feedback', 'needs_attention', 'reviewed', 'page',
        // The number the sheet printed (null when not read) and what to show for it.
        'question_label', 'display_label', 'label_from_sheet', 'label_inferred',
    ];

    /** @var array<int,string> What the frontend reads from `paper`; the server may send more. */
    public const ANSWER_KEY_PAPER = ['id', 'paper_name', 'exam_type', 'standard_name', 'grade_name', 'subject_name'];

    /** @var array<int,string> */
    public const ANSWER_KEY = [
        'paper', 'applies_to_class', 'eligible_students', 'ai_warning', 'total_marks', 'objective_count', 'subjective_count',
        'question_count', 'missing_model_answers', 'questions',
    ];

    /** @var array<int,string> */
    public const ANSWER_KEY_QUESTION = ['question_no', 'question_title', 'kind', 'max_marks', 'correct_letters', 'has_model_answer'];

    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->batchId = $this->makeBatch(['status' => 'Review']);
        Queue::fake();
    }

    /** @param  array<int,string>  $expected */
    private function assertKeys(array $expected, array $actual, string $what): void
    {
        $this->assertEqualsCanonicalizing($expected, array_keys($actual), "{$what}: the keys the frontend reads must be exactly these");
    }

    public function test_a_batch_detail_has_the_batch_sheet_and_roster_shapes_the_screens_read(): void
    {
        $this->awaitingSheet($this->batchId, ['ai_result_json' => json_encode(['identity' => ['issue' => 'conflict', 'candidates' => [101, 102], 'page' => 2]])]);
        $this->makeSheet($this->batchId, ['student_id' => 102]);

        $data = $this->getJson("/api/exam-evaluation/batches/{$this->batchId}?sub_institute_id=" . self::TENANT)->assertOk()->json('data');

        $this->assertKeys(['batch', 'sheets', 'roster'], $data, 'batch detail');
        $this->assertKeys(self::BATCH, $data['batch'], 'batch');
        $this->assertKeys(self::SHEET, $data['sheets'][0], 'sheet');
        $this->assertKeys(self::ROSTER_STUDENT, $data['roster'][0], 'roster student');
        // Every sheet carries the same keys, whatever state it is in.
        foreach ($data['sheets'] as $sheet) {
            $this->assertKeys(self::SHEET, $sheet, 'sheet #' . $sheet['id']);
        }
    }

    public function test_a_sheet_detail_has_the_sheet_context_and_answer_shapes_the_review_page_reads(): void
    {
        $sheetId = $this->makeSheet($this->batchId);

        $data = $this->getJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT)->assertOk()->json('data');

        $this->assertKeys(['sheet', 'context', 'answers'], $data, 'sheet detail');
        $this->assertKeys(self::SHEET, $data['sheet'], 'sheet');
        $this->assertKeys(self::CONTEXT, $data['context'], 'context');
        $this->assertCount(3, $data['answers']);
        $this->assertKeys(self::ANSWER, $data['answers'][0], 'answer');
    }

    public function test_the_answer_key_summary_has_the_shape_the_new_evaluation_panel_reads(): void
    {
        $data = $this->getJson('/api/exam-evaluation/answer-key/1?sub_institute_id=' . self::TENANT)->assertOk()->json('data');

        $this->assertKeys(self::ANSWER_KEY, $data, 'answer key');
        $this->assertEmpty(array_diff(self::ANSWER_KEY_PAPER, array_keys($data['paper'])), 'paper lacks a key the frontend reads');
        $this->assertKeys(self::ANSWER_KEY_QUESTION, $data['questions'][0], 'answer-key question');
    }

    public function test_the_bulk_confirmation_response_is_a_batch_detail_plus_what_was_done(): void
    {
        $sheetId = $this->awaitingSheet($this->batchId);

        $data = $this->postJson("/api/exam-evaluation/batches/{$this->batchId}/confirm-identities", $this->body(['sheet_ids' => [$sheetId]]))
            ->assertOk()->json('data');

        $this->assertKeys(['batch', 'sheets', 'roster', 'confirmed', 'skipped'], $data, 'bulk confirmation');
        $this->assertSame([$sheetId], $data['confirmed']);
    }

    public function test_an_upload_response_adds_the_counts_the_screen_reports(): void
    {
        $response = $this->post("/api/exam-evaluation/batches/{$this->batchId}/sheets", $this->body([
            'sheets' => [\Illuminate\Http\UploadedFile::fake()->createWithContent('x.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF")],
        ]), ['Accept' => 'application/json'])->assertOk();

        $this->assertKeys(['batch', 'sheets', 'roster', 'uploaded', 'rejected'], $response->json('data'), 'upload');

        DB::table('exam_evaluation_sheet')->get()->each(fn ($row) => @unlink(storage_path('app/exam_evaluation/' . $row->file_name)));
    }

    public function test_the_error_envelope_is_the_one_the_client_unwraps(): void
    {
        $body = $this->getJson('/api/exam-evaluation/sheets/999999?sub_institute_id=' . self::TENANT)->assertStatus(404)->json();

        $this->assertKeys(['status_code', 'message', 'errors'], $body, 'failure envelope');
        $this->assertSame(0, $body['status_code']);

        $ok = $this->getJson('/api/exam-evaluation/batches?sub_institute_id=' . self::TENANT)->json();
        $this->assertKeys(['status_code', 'message', 'data'], $ok, 'success envelope');
        $this->assertSame(1, $ok['status_code']);
    }

    public function test_every_status_the_frontend_has_a_style_for_is_one_the_backend_can_produce(): void
    {
        // The frontend's SheetStatus union (types.ts) and style table (status-styles.ts).
        $frontend = ['Identifying', 'Awaiting identity', 'Pending', 'Processing', 'Evaluated', 'Needs review', 'Approved', 'Failed'];

        $this->assertSame(
            ['Identifying', 'Awaiting identity'],
            \App\Services\Evaluation\SheetIdentity::PRE_EVALUATION
        );
        $this->assertEqualsCanonicalizing(
            ['Identifying', 'Pending', 'Processing'],
            \App\Services\Evaluation\SheetIdentity::IN_FLIGHT
        );
        $this->assertEmpty(array_diff(\App\Services\Evaluation\SheetIdentity::PRE_EVALUATION, $frontend));
        $this->assertEmpty(array_diff(\App\Services\Evaluation\SheetIdentity::IN_FLIGHT, $frontend));
    }
}
