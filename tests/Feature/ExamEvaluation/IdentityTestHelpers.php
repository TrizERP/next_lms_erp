<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\EvaluateAnswerSheetJob;
use App\Jobs\IdentifyAnswerSheetJob;
use App\Services\Evaluation\AnswerKeyService;
use App\Services\Evaluation\AnswerSheetReaderService;
use App\Services\Evaluation\AnswerSheetScoringService;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Evaluation\StudentMatcherService;
use App\Services\Homework\HomeworkAnnotatedPdfService;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeGeminiClient;

/**
 * Shared scaffolding for the student-identification tests: extra students on (and
 * off) the class list, a scan on disk, and the two background jobs run for real
 * against a scripted model. No real model, no shared database.
 *
 * Students the paper seeds: 101 Asha Patel (GR101, roll 1, section A), 102 Ravi
 * Shah (GR102, roll 2, section B), both in Class 7; 201 belongs to another school.
 */
trait IdentityTestHelpers
{
    private string $scanPath = '';

    protected function setUpScan(): void
    {
        $this->scanPath = (string) tempnam(sys_get_temp_dir(), 'exam-eval-identity-');
        file_put_contents($this->scanPath, '%PDF-1.4 test scan');
    }

    protected function tearDownScan(): void
    {
        if ($this->scanPath !== '') {
            @unlink($this->scanPath);
        }
    }

    protected function addStudent(
        int $id,
        string $first,
        string $last,
        string $enrollment,
        string $roll,
        int $standard = 1,
        ?string $dropCode = null,
        int $section = 1,
        int $tenant = self::TENANT
    ): void {
        DB::table('tblstudent')->insert([
            'id' => $id, 'first_name' => $first, 'middle_name' => '', 'last_name' => $last,
            'enrollment_no' => $enrollment, 'sub_institute_id' => $tenant,
        ]);
        DB::table('tblstudent_enrollment')->insert([
            'student_id' => $id, 'syear' => self::SYEAR, 'standard_id' => $standard, 'grade_id' => 1,
            'section_id' => $section, 'roll_no' => $roll, 'drop_code' => $dropCode, 'term_id' => 1, 'sub_institute_id' => $tenant,
        ]);
    }

    /** A second Ravi Shah in the same class (the other section), for the ambiguous-name cases. */
    protected function addSecondRaviShah(): void
    {
        $this->addStudent(103, 'Ravi', 'Shah', 'GR103', '3', 1, null, 1);
    }

    /**
     * Takes away the database's own one-sheet-per-student guard, as it is on a
     * database where the (batch_id, student_id) unique index has not been
     * applied -- so a test can stage the duplicate that guard would otherwise
     * stop, and show the application catches it too.
     */
    protected function withoutTheUniqueIndex(): void
    {
        \Illuminate\Support\Facades\Schema::table('exam_evaluation_sheet', fn ($table) => $table->dropUnique('ees_batch_student_unique'));
    }

    /** A Class 8 child at the same school: on the school's roll, not eligible for a Class 7 paper. */
    protected function addClassEightStudent(): void
    {
        $this->addStudent(104, 'Neha', 'Desai', 'GR104', '1', 2);
    }

    /** A Class 7 child who has left: still in the school's records, no longer eligible. */
    protected function addDroppedStudent(): void
    {
        $this->addStudent(105, 'Kabir', 'Nair', 'GR105', '5', 1, 'LEFT');
    }

    /**
     * A sheet as the upload leaves it: queued for its identity read, nothing known yet.
     *
     * @param  array<string,mixed>  $over
     */
    protected function identifyingSheet(int $batchId, array $over = []): int
    {
        return $this->bareSheet($batchId, $over + ['status' => 'Identifying']);
    }

    /**
     * A sheet that has been read and matched and now waits for a teacher.
     *
     * @param  array<string,mixed>  $over
     */
    protected function awaitingSheet(int $batchId, array $over = []): int
    {
        return $this->bareSheet($batchId, $over + [
            'status' => 'Awaiting identity', 'student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confidence' => 95,
            'detected_enrollment_no' => 'GR101', 'detected_roll_no' => '1', 'detected_student_name' => 'Asha Patel',
        ]);
    }

    /**
     * A sheet with no answers yet and no confirmation: the state before any marking.
     *
     * @param  array<string,mixed>  $over
     */
    protected function bareSheet(int $batchId, array $over = []): int
    {
        $sheetId = $this->makeSheet($batchId, $over + [
            'student_id' => null, 'identity_source' => null, 'identity_confidence' => null,
            'identity_confirmed_at' => null, 'identity_confirmed_by' => null,
            'ai_total' => null, 'percentage' => null,
        ]);
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->delete();

        return $sheetId;
    }

    protected function storageDouble(): ExamEvaluationStorage
    {
        $scan = $this->scanPath;

        return new class($scan) extends ExamEvaluationStorage {
            public function __construct(private string $path)
            {
            }

            public function sheetPath(string $fileName): ?string
            {
                return $this->path;
            }

            public function putAnnotated(int $sheetId, string $pdfBinary): string
            {
                return 'annotated-test.pdf';
            }
        };
    }

    protected function identify(int $sheetId, FakeGeminiClient $gemini, ?StudentMatcherService $matcher = null, ?ExamEvaluationStorage $storage = null): void
    {
        (new IdentifyAnswerSheetJob($sheetId))->handle(
            new AnswerSheetReaderService($gemini),
            $matcher ?? new StudentMatcherService(),
            $storage ?? $this->storageDouble()
        );
    }

    protected function evaluate(int $sheetId, FakeGeminiClient $gemini, ?StudentMatcherService $matcher = null): void
    {
        $annotator = new class extends HomeworkAnnotatedPdfService {
            public function annotate(string $originalAbsolutePath, string $mimeType, array $annotations): string
            {
                return '%PDF-1.4 marked up';
            }
        };

        (new EvaluateAnswerSheetJob($sheetId))->handle(
            app(AnswerKeyService::class),
            new AnswerSheetReaderService($gemini),
            new AnswerSheetScoringService($gemini),
            $matcher ?? new StudentMatcherService(),
            $annotator,
            $this->storageDouble()
        );
    }

    /**
     * A matcher that runs a callback at the moment it is asked to match -- the
     * point in both jobs between "the model has answered" and "the result is
     * saved", where a teacher can act while a slow job is still in flight.
     */
    protected function matcherThat(\Closure $duringMatch): StudentMatcherService
    {
        return new class($duringMatch) extends StudentMatcherService {
            public function __construct(private \Closure $duringMatch)
            {
            }

            public function match(array $detected, array $roster): array
            {
                ($this->duringMatch)();

                return parent::match($detected, $roster);
            }
        };
    }

    /** @return array<string,mixed> What the identity-only read returns. */
    protected function identityReply(array $over = []): array
    {
        return $over + ['roll_no' => '1', 'enrollment_no' => 'GR101', 'name' => 'Asha Patel', 'confidence' => 90, 'page' => 1, 'multiple_students' => false];
    }

    /** @return array<string,mixed> What the full vision read returns for a clean three-question sheet. */
    protected function readerReply(array $student = []): array
    {
        return [
            'student' => $student + ['roll_no' => '1', 'enrollment_no' => 'GR101', 'name' => 'Asha Patel', 'confidence' => 90],
            'responses' => [
                ['question_no' => 1, 'attempted' => true, 'selected_options' => ['A'], 'answer_text' => '', 'page' => 1, 'box_2d' => [100, 100, 200, 200]],
                ['question_no' => 2, 'attempted' => true, 'selected_options' => [], 'answer_text' => 'Plants use sunlight and water', 'page' => 2, 'box_2d' => null],
                ['question_no' => 3, 'attempted' => true, 'selected_options' => [], 'answer_text' => 'It speeds up a reaction', 'page' => 2, 'box_2d' => null],
            ],
        ];
    }

    /** @return array<string,mixed> The written-answer grader's reply (it is only asked about Q2). */
    protected function graderReply(float $marks = 3): array
    {
        return ['results' => [['question_no' => 2, 'marks' => $marks, 'confidence' => 80, 'remark' => 'Two points made.']]];
    }

    protected function sheetRow(int $sheetId): object
    {
        return DB::table('exam_evaluation_sheet')->find($sheetId);
    }

    /** @return array<string,mixed> The identity block the first stage left in ai_result_json. */
    protected function identityBlock(int $sheetId): array
    {
        $json = json_decode((string) $this->sheetRow($sheetId)->ai_result_json, true);

        return is_array($json) ? ($json['identity'] ?? []) : [];
    }
}
