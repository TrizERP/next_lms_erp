<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\EvaluateAnswerSheetJob;
use App\Services\Evaluation\AnswerKeyService;
use App\Services\Evaluation\AnswerSheetReaderService;
use App\Services\Evaluation\AnswerSheetScoringService;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Evaluation\ExamSheetMarkup;
use App\Services\Evaluation\QuestionLabel;
use App\Services\Evaluation\StudentMatcherService;
use App\Services\Homework\HomeworkAnnotatedPdfService;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Fpdi;
use Tests\Support\FakeGeminiClient;

/**
 * The review page's three promises, pinned at their root:
 *
 *  1. A question is shown by the number the SHEET printed (Q.1(A), Q.2 ...), not by its
 *     position in the paper and never by a database id. The position (`question_no`) still
 *     keys the answer to the marking key.
 *  2. The marked-up PDF is drawn from the marks that count -- a teacher's, once they have set
 *     one -- with partial credit shown as partial, and the original scan never touched.
 *  3. Totals are the server's, recomputed from the stored marks.
 *
 * The paper has three questions (an MCQ, a 5-mark written, a 3-mark written), laid out on the
 * sheet as Q.1(A), Q.1(B) and Q.2: position 3 is the sheet's "Q.2", which is exactly the
 * mismatch that used to show up as "Q3".
 */
class QuestionNumberingAndMarkupTest extends ExamEvaluationTestCase
{
    private int $batchId;
    private string $dir;
    private string $original;
    /** @var array<int,array<int,array<string,mixed>>> Every set of annotations the annotator was asked to draw. */
    private array $drawn = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->batchId = $this->makeBatch();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exam-eval-markup-' . uniqid();
        mkdir($this->dir . DIRECTORY_SEPARATOR . 'annotated', 0775, true);
        $this->original = $this->dir . DIRECTORY_SEPARATOR . 'original-scan.pdf';
        $this->writeRealPdf($this->original);

        $this->useStorageAndRecorder();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,annotated/}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) && @unlink($file);
        }
        @rmdir($this->dir . '/annotated');
        @rmdir($this->dir);
        parent::tearDown();
    }

    // -- fixtures -------------------------------------------------------------------------

    private function writeRealPdf(string $path): void
    {
        $pdf = new Fpdi('P', 'pt', [600, 800]);
        foreach (['Page one: Q.1(A) Q.1(B)', 'Page two: Q.2'] as $text) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Text(40, 60, $text);
        }
        $pdf->Output('F', $path);
    }

    /** The real storage rules (names, annotated folder), rooted in a temp folder, with one known original. */
    private function useStorageAndRecorder(): void
    {
        $dir = $this->dir;
        $original = $this->original;

        $this->app->instance(ExamEvaluationStorage::class, new class($dir, $original) extends ExamEvaluationStorage {
            public function __construct(private string $root, private string $original)
            {
            }

            public function directory(): string
            {
                return $this->root;
            }

            public function annotatedDirectory(): string
            {
                return $this->root . DIRECTORY_SEPARATOR . 'annotated';
            }

            public function sheetPath(string $fileName): ?string
            {
                return $fileName === 'original-scan.pdf' ? $this->original : null;
            }
        });

        $test = $this;
        $this->app->instance(HomeworkAnnotatedPdfService::class, new class($test) extends HomeworkAnnotatedPdfService {
            public function __construct(private QuestionNumberingAndMarkupTest $test)
            {
            }

            public function annotate(string $originalAbsolutePath, string $mimeType, array $annotations): string
            {
                $this->test->record($annotations);

                return parent::annotate($originalAbsolutePath, $mimeType, $annotations);
            }
        });
    }

    /** @internal called by the recording annotator */
    public function record(array $annotations): void
    {
        $this->drawn[] = $annotations;
    }

    /** @return array<int,array<string,mixed>> The last drawing, keyed by question position. */
    private function lastDrawn(): array
    {
        $last = end($this->drawn) ?: [];

        return array_column($last, null, 'question_no');
    }

    /** A sheet on the real PDF, labelled the way the printed sheet numbers it. */
    private function labelledSheet(array $labels = [1 => 'Q.1(A)', 2 => 'Q.1(B)', 3 => 'Q.2']): int
    {
        $sheetId = $this->makeSheet($this->batchId, ['file_name' => 'original-scan.pdf']);

        foreach ($labels as $position => $label) {
            DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', $position)
                ->update(['question_label' => $label, 'page' => $position === 3 ? 2 : 1, 'box_2d' => json_encode([100 * $position, 120, 100 * $position + 60, 500])]);
        }

        return $sheetId;
    }

    private function answer(int $sheetId, int $position): object
    {
        return DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', $position)->first();
    }

    private function sheetAnswers(int $sheetId): array
    {
        return collect($this->getJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT)->assertOk()->json('data.answers'))->keyBy('question_no')->all();
    }

    private function preview(int $sheetId, array $marks)
    {
        return $this->post("/api/exam-evaluation/sheets/{$sheetId}/preview", $this->body(['marks' => $marks]), ['Accept' => 'application/pdf, application/json']);
    }

    // -- 1 & 2. numbering ---------------------------------------------------------------------

    public function test_the_label_the_sheet_printed_is_shown_and_the_paper_position_is_kept_for_mapping(): void
    {
        $sheetId = $this->labelledSheet();

        $answers = $this->sheetAnswers($sheetId);

        $this->assertSame('Q.1(A)', $answers[1]['display_label']);
        $this->assertSame('Q.1(B)', $answers[2]['display_label']);
        $this->assertSame('Q.2', $answers[3]['display_label'], 'position 3 is the sheet\'s Q.2, not "Q3"');
        $this->assertTrue($answers[3]['label_from_sheet']);
        // Position and question id stay what they were: they are how the answer finds its key.
        $this->assertSame([1, 2, 3], array_column($answers, 'question_no'));
        $this->assertSame([11, 12, 13], array_column($answers, 'question_id'));
    }

    public function test_without_a_printed_label_the_number_is_inferred_from_the_layout_and_says_so_never_the_database_id(): void
    {
        $sheetId = $this->makeSheet($this->batchId); // no labels at all: sheets read before labels existed

        $answers = $this->sheetAnswers($sheetId);

        // One MCQ then two written questions: Q.1, Q.2, Q.3 -- as a printed paper numbers them.
        $this->assertNull($answers[2]['question_label']);
        $this->assertFalse($answers[2]['label_from_sheet'], 'the screen must be able to say the number was not read');
        $this->assertTrue($answers[2]['label_inferred']);
        $this->assertSame(['Q.1', 'Q.2', 'Q.3'], array_column($answers, 'display_label'));
        $this->assertNotContains('Q12', array_column($answers, 'display_label'), 'question_id 12 is a database id, never a number to show');
    }

    public function test_a_run_of_objective_questions_is_numbered_as_parts_of_one_question_like_the_printed_sheet(): void
    {
        // The paper in question: five MCQs then three written questions -> Q.1(A)..(E), Q.2, Q.3, Q.4.
        $this->assertSame(
            [1 => 'Q.1(A)', 2 => 'Q.1(B)', 3 => 'Q.1(C)', 4 => 'Q.1(D)', 5 => 'Q.1(E)', 6 => 'Q.2', 7 => 'Q.3', 8 => 'Q.4'],
            QuestionLabel::inferFromLayout([1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => false, 7 => false, 8 => false])
        );
        // A lone MCQ is its own number; two or more in a row are parts of one.
        $this->assertSame([1 => 'Q.1', 2 => 'Q.2(A)', 3 => 'Q.2(B)', 4 => 'Q.3'], QuestionLabel::inferFromLayout([1 => false, 2 => true, 3 => true, 4 => false]));
    }

    public function test_a_label_that_was_really_read_is_never_mixed_with_inferred_ones(): void
    {
        $sheetId = $this->labelledSheet([2 => 'Q.1(B)']); // only one of three was read

        $answers = $this->sheetAnswers($sheetId);

        $this->assertSame('Q.1(B)', $answers[2]['display_label']);
        $this->assertFalse($answers[2]['label_inferred']);
        $this->assertSame('Q1', $answers[1]['display_label'], 'the rest fall back to position, not to a guess');
        $this->assertSame('Q3', $answers[3]['display_label']);
    }

    public function test_the_reader_is_told_that_item_numbers_are_ours_and_is_asked_for_the_printed_one(): void
    {
        $gemini = new FakeGeminiClient([$this->readerReply(), $this->graderReply()]);
        $sheetId = $this->pendingSheet();

        $this->runJob($sheetId, $gemini);

        $prompt = $gemini->promptText(0);
        $this->assertStringContainsString('Item 3 [', $prompt);
        $this->assertStringNotContainsString('Q3 [', $prompt, 'a bare "Q3" invites the model to match it to a printed Q.3');
        $this->assertStringContainsString('NOT necessarily the number printed on the sheet', $prompt);
        $this->assertStringContainsString('question_label', $prompt);
    }

    public function test_labels_read_from_the_sheet_are_stored_with_the_answers_and_survive_a_rerun(): void
    {
        $sheetId = $this->pendingSheet();

        $this->runJob($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));

        $this->assertSame(['Q.1(A)', 'Q.1(B)', 'Q.2'], DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->pluck('question_label')->all());
        $this->assertSame([1, 2, 3], DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->pluck('question_no')->map(fn ($n) => (int) $n)->all());

        // Re-run: same rows (no duplicates, no reordering), label still tied to its position.
        DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['status' => 'Pending']);
        $this->runJob($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply()]));

        $rows = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->get();
        $this->assertCount(3, $rows);
        $this->assertSame([11, 12, 13], $rows->pluck('question_id')->map(fn ($n) => (int) $n)->all());
        $this->assertSame('Q.2', $rows[2]->question_label);
    }

    public function test_an_unreliable_label_is_not_stored_rather_than_guessed(): void
    {
        $reply = $this->readerReply();
        $reply['responses'][0]['question_label'] = 'Q.2';   // two answers both claim Q.2 ...
        $reply['responses'][2]['question_label'] = 'Q.2';   // ... so neither can be trusted
        $reply['responses'][1]['question_label'] = 'see page two';   // not a question number at all
        $sheetId = $this->pendingSheet();

        $this->runJob($sheetId, new FakeGeminiClient([$reply, $this->graderReply()]));

        $this->assertSame([null, null, null], DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->pluck('question_label')->all());
        $this->assertSame('Q.3', $this->sheetAnswers($sheetId)[3]['display_label'], 'inferred from the layout, flagged as such');
        $this->assertTrue($this->sheetAnswers($sheetId)[3]['label_inferred']);
    }

    public function test_label_normalisation(): void
    {
        $this->assertSame('Q.1(A)', QuestionLabel::normalise('Q.1(A)'));
        $this->assertSame('Q.1(A)', QuestionLabel::normalise('q 1 (a)'));
        $this->assertSame('Q.1(A)', QuestionLabel::normalise('1-A'));
        $this->assertSame('Q.2', QuestionLabel::normalise('Question 2'));
        $this->assertSame('Q.2', QuestionLabel::normalise(2));
        $this->assertSame('Q.3(A)(ii)', QuestionLabel::normalise('3(a)(ii)'));
        foreach (['', '  ', 'page 2', 'Q.', 'Q.A', null, ['1'], '1234'] as $junk) {
            $this->assertNull(QuestionLabel::normalise($junk), json_encode($junk));
        }
        $this->assertSame([1 => null, 2 => 'Q.2', 3 => null], QuestionLabel::withoutDuplicates([1 => 'Q.1', 2 => 'Q.2', 3 => 'Q.1']));
    }

    // -- 5 & 6. marks, validation, live preview ---------------------------------------------

    public function test_the_preview_draws_the_teachers_unsaved_mark_with_partial_credit_shown_as_partial(): void
    {
        $sheetId = $this->labelledSheet();

        $response = $this->preview($sheetId, [['question_no' => 2, 'teacher_marks' => 4]]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $q2 = $this->lastDrawn()[2];
        $this->assertSame('partially_correct', $q2['status'], '4 of 5 is neither a tick nor a cross');
        $this->assertSame('4/5', $q2['score_text']);
        $this->assertSame('(Teacher)', $q2['source_tag']);
        $this->assertSame('Q.1(B)', $q2['label']);
        // The AI's own marks elsewhere stay the AI's, and are tagged so.
        $this->assertSame('(AI)', $this->lastDrawn()[1]['source_tag']);
        $this->assertSame('correct', $this->lastDrawn()[1]['status']);
    }

    public function test_a_teacher_mark_changes_the_drawing_between_full_partial_and_zero(): void
    {
        $sheetId = $this->labelledSheet();

        foreach ([[5, 'correct', '5/5'], [2.5, 'partially_correct', '2.5/5'], [0, 'wrong', '0/5']] as [$marks, $status, $text]) {
            $this->preview($sheetId, [['question_no' => 2, 'teacher_marks' => $marks]])->assertOk();
            $this->assertSame($status, $this->lastDrawn()[2]['status'], "teacher gave {$marks}");
            $this->assertSame($text, $this->lastDrawn()[2]['score_text']);
        }
    }

    public function test_clearing_a_mark_in_the_preview_falls_back_to_the_ai_mark(): void
    {
        $sheetId = $this->labelledSheet();
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 2)->update(['teacher_marks' => 1]);

        $this->preview($sheetId, [['question_no' => 2, 'teacher_marks' => null]])->assertOk();

        $this->assertSame('3/5', $this->lastDrawn()[2]['score_text']);
        $this->assertSame('(AI)', $this->lastDrawn()[2]['source_tag']);
    }

    public function test_an_answer_the_ai_could_not_grade_is_not_drawn_as_a_cross(): void
    {
        $sheetId = $this->labelledSheet(); // question 3: AI 0, confidence 0 -- "please mark it by hand"

        $this->preview($sheetId, [])->assertOk();

        $q3 = $this->lastDrawn()[3];
        $this->assertSame('pending', $q3['status']);
        $this->assertSame('awaiting teacher mark', $q3['score_text']);

        $this->preview($sheetId, [['question_no' => 3, 'teacher_marks' => 0]])->assertOk();
        $this->assertSame('wrong', $this->lastDrawn()[3]['status'], 'a teacher\'s zero IS a verdict');
    }

    public function test_invalid_marks_are_refused_by_the_preview_and_by_the_save_never_clamped(): void
    {
        $sheetId = $this->labelledSheet();
        $drawnBefore = count($this->drawn);

        foreach ([['question_no' => 2, 'teacher_marks' => 6], ['question_no' => 2, 'teacher_marks' => -1], ['question_no' => 2, 'teacher_marks' => 'abc'], ['question_no' => 9, 'teacher_marks' => 1]] as $bad) {
            $this->preview($sheetId, [$bad])->assertStatus(422);
        }
        $this->assertCount($drawnBefore, $this->drawn, 'a refused preview draws nothing');

        // The message names the question the way the teacher sees it.
        $this->assertStringContainsString('Q.1(B): marks must be between 0 and 5', $this->preview($sheetId, [['question_no' => 2, 'teacher_marks' => 6]])->json('message'));

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 6]]])->assertStatus(422);
        $this->assertNull($this->answer($sheetId, 2)->teacher_marks);
    }

    public function test_the_preview_changes_nothing_that_is_stored(): void
    {
        $sheetId = $this->labelledSheet();
        $sheetBefore = DB::table('exam_evaluation_sheet')->find($sheetId);
        $answersBefore = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('id')->get();
        $filesBefore = glob($this->dir . '/annotated/*');

        $this->preview($sheetId, [['question_no' => 2, 'teacher_marks' => 4]])->assertOk();

        $this->assertEquals($sheetBefore, DB::table('exam_evaluation_sheet')->find($sheetId));
        $this->assertEquals($answersBefore, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('id')->get());
        $this->assertSame($filesBefore, glob($this->dir . '/annotated/*'), 'no file was written');
    }

    public function test_another_schools_sheet_cannot_be_previewed(): void
    {
        $sheetId = $this->labelledSheet();

        $this->post("/api/exam-evaluation/sheets/{$sheetId}/preview", $this->body(['sub_institute_id' => self::OTHER_TENANT, 'marks' => []]), ['Accept' => 'application/json'])->assertStatus(404);
    }

    // -- 7, 8, 9. the saved copy ---------------------------------------------------------------

    public function test_saving_a_mark_redraws_the_saved_marked_up_copy_and_leaves_the_original_scan_untouched(): void
    {
        $sheetId = $this->labelledSheet();
        $hash = md5_file($this->original);

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4, 'teacher_feedback' => 'Missed the light reaction.']]])->assertOk();

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertNotEmpty($sheet->annotated_file_name, 'a saved marked-up copy exists');
        $saved = $this->dir . '/annotated/' . $sheet->annotated_file_name;
        $this->assertFileExists($saved);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($saved));
        $this->assertSame('4/5', $this->lastDrawn()[2]['score_text']);
        $this->assertSame('(Teacher)', $this->lastDrawn()[2]['source_tag']);
        $this->assertSame($hash, md5_file($this->original), 'the uploaded scan is only ever read');
        $this->assertNotSame(realpath($saved), realpath($this->original));
    }

    public function test_the_real_pdf_keeps_every_page_and_differs_when_a_mark_differs(): void
    {
        $sheetId = $this->labelledSheet();
        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $answers = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->get();
        $markup = app(ExamSheetMarkup::class);

        $ai = $markup->render($sheet, $answers);
        $teacher = $markup->render($sheet, $answers, [2 => 4.0]);

        $this->assertNotNull($ai);
        $this->assertNotSame($ai, $teacher, 'a different mark draws a different page');
        $this->assertSame(2, preg_match_all('#/Type\s*/Page\b#', $teacher), 'both pages of the original are there');
    }

    public function test_saving_and_reopening_restores_marks_feedback_totals_and_the_saved_copy(): void
    {
        $sheetId = $this->labelledSheet();

        $this->review($sheetId, ['marks' => [
            ['question_no' => 2, 'teacher_marks' => 4, 'teacher_feedback' => 'Good, but incomplete.'],
            ['question_no' => 3, 'teacher_marks' => 1.5],
        ]])->assertOk();

        $data = $this->getJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT)->assertOk()->json('data');
        $answers = collect($data['answers'])->keyBy('question_no');

        $this->assertEquals(4.0, $answers[2]['teacher_marks']);
        $this->assertSame('Good, but incomplete.', $answers[2]['teacher_feedback']);
        $this->assertEquals(3.0, $answers[2]['ai_marks'], 'the AI\'s proposal is still there, separately');
        $this->assertEquals(1.5, $answers[3]['teacher_marks']);
        // 2 (AI, Q1 unreviewed) + 4 + 1.5 = 7.5 of 10 -- computed by the server from the stored marks.
        $this->assertEquals(7.5, $data['sheet']['teacher_total']);
        $this->assertEquals(75.0, $data['sheet']['percentage']);
        $this->assertTrue($data['sheet']['has_annotated']);
        $this->assertSame('Q.2', $answers[3]['display_label']);
    }

    public function test_the_server_recomputes_the_total_and_ignores_one_the_client_sends(): void
    {
        $sheetId = $this->labelledSheet();

        $this->review($sheetId, ['teacher_total' => 999, 'percentage' => 100, 'marks' => [['question_no' => 2, 'teacher_marks' => 0.5]]])->assertOk();

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertEquals(2.5, $sheet->teacher_total);      // 2 + 0.5 + AI 0
        $this->assertEquals(25.0, $sheet->percentage);
        $this->assertEquals(5.0, $sheet->ai_total, 'the AI total is not disturbed');
    }

    public function test_a_save_changes_only_the_teachers_columns_so_page_box_label_and_order_are_kept(): void
    {
        $sheetId = $this->labelledSheet();
        $before = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4]]])->assertOk();

        $after = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertCount(3, $after);
        foreach ($before as $i => $row) {
            foreach (['id', 'question_no', 'question_id', 'question_label', 'page', 'box_2d', 'ai_marks', 'max_marks'] as $column) {
                $this->assertEquals($row[$column], $after[$i][$column], "{$column} of answer " . ($i + 1));
            }
        }
        // And the annotation drawn for Q.2 sits where THAT answer was found.
        $q3 = $this->lastDrawn()[3];
        $this->assertSame(2, $q3['page']);
        $this->assertSame([300, 120, 360, 500], $q3['box_2d']);
        $this->assertSame(1, $this->lastDrawn()[2]['page']);
        $this->assertSame([200, 120, 260, 500], $this->lastDrawn()[2]['box_2d']);
    }

    public function test_a_missing_box_is_not_invented(): void
    {
        $sheetId = $this->labelledSheet();
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 2)->update(['box_2d' => null]);

        $this->preview($sheetId, [])->assertOk();

        $this->assertNull($this->lastDrawn()[2]['box_2d']);
        $this->assertSame('Q.1(B)', $this->lastDrawn()[2]['label'], 'unplaced, but still named');
    }

    // -- 10. re-evaluation ---------------------------------------------------------------------

    public function test_a_rerun_keeps_the_teachers_marks_and_the_saved_copy_shows_them(): void
    {
        $sheetId = $this->pendingSheet();
        $this->runJob($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply(3)]));
        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4, 'teacher_feedback' => 'Mine.']]])->assertOk();

        DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['status' => 'Pending']);
        $this->runJob($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply(1)]));

        $q2 = $this->answer($sheetId, 2);
        $this->assertEquals(4.0, $q2->teacher_marks);
        $this->assertSame('Mine.', $q2->teacher_feedback);
        $this->assertEquals(1.0, $q2->ai_marks, 'the AI column refreshed');
        // The redrawn copy shows the teacher's 4, not the AI's new 1.
        $this->assertSame('4/5', $this->lastDrawn()[2]['score_text']);
        $this->assertSame('(Teacher)', $this->lastDrawn()[2]['source_tag']);
    }

    public function test_a_stale_job_cannot_redraw_or_overwrite_an_approved_sheet(): void
    {
        $sheetId = $this->labelledSheet();
        $this->review($sheetId, ['marks' => [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 3]], 'approve' => true])->assertOk();
        $before = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('id')->get();

        $this->runJob($sheetId, new FakeGeminiClient([$this->readerReply(), $this->graderReply(0)]));

        $this->assertEquals($before, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('id')->get());
        $this->assertSame('Approved', DB::table('exam_evaluation_sheet')->find($sheetId)->status);
    }

    // -- 11. approval and publishing still work ---------------------------------------------------

    public function test_approval_still_needs_every_flagged_question_marked_and_then_publishes(): void
    {
        $sheetId = $this->labelledSheet();

        $this->review($sheetId, ['approve' => true])->assertStatus(422);

        $this->review($sheetId, ['approve' => true, 'marks' => [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 3]]])->assertOk();
        $this->assertSame('Approved', DB::table('exam_evaluation_sheet')->find($sheetId)->status);

        $this->postJson("/api/exam-evaluation/batches/{$this->batchId}/publish", $this->body())->assertOk();
        $this->assertSame(9, (int) DB::table('lms_offline_exam')->where('student_id', 101)->value('obtain_marks'));   // 2 + 4 + 3
    }

    // -- helpers for the job ------------------------------------------------------------------------

    private function pendingSheet(): int
    {
        $sheetId = $this->makeSheet($this->batchId, ['status' => 'Pending', 'student_id' => null, 'identity_source' => null, 'ai_total' => null, 'percentage' => null, 'file_name' => 'original-scan.pdf']);
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->delete();

        return $sheetId;
    }

    /** @return array<string,mixed> A clean read of a sheet that numbers its questions Q.1(A), Q.1(B), Q.2. */
    private function readerReply(): array
    {
        return [
            'student' => ['roll_no' => '1', 'enrollment_no' => 'GR101', 'name' => 'Asha Patel', 'confidence' => 90],
            'responses' => [
                ['question_no' => 1, 'question_label' => 'Q.1(A)', 'attempted' => true, 'selected_options' => ['A'], 'answer_text' => '', 'page' => 1, 'box_2d' => [100, 100, 200, 200]],
                ['question_no' => 2, 'question_label' => 'Q.1(B)', 'attempted' => true, 'selected_options' => [], 'answer_text' => 'Plants use sunlight and water', 'page' => 1, 'box_2d' => [300, 100, 400, 500]],
                ['question_no' => 3, 'question_label' => 'Q.2', 'attempted' => true, 'selected_options' => [], 'answer_text' => 'It speeds up a reaction', 'page' => 2, 'box_2d' => [100, 100, 200, 500]],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function graderReply(float $marks = 3): array
    {
        return ['results' => [['question_no' => 2, 'marks' => $marks, 'confidence' => 80, 'remark' => 'Two points made.']]];
    }

    private function runJob(int $sheetId, FakeGeminiClient $gemini): void
    {
        (new EvaluateAnswerSheetJob($sheetId))->handle(
            app(AnswerKeyService::class),
            new AnswerSheetReaderService($gemini),
            new AnswerSheetScoringService($gemini),
            new StudentMatcherService(),
            app(HomeworkAnnotatedPdfService::class),
            app(ExamEvaluationStorage::class)
        );
    }
}
