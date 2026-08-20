<?php

namespace Tests\Feature\Pal;

use App\Services\PAL\Intelligence\LearnerStateEngine;
use App\Services\PAL\Pedagogy\PedagogyOrchestrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Real end-to-end validation of the PAL V4 student journey, per the master
 * spec's own success criteria: "Verify database state after every step" and
 * "demonstrate ... student starts with learner state ... answers a question
 * ... real assessment result is stored ... mastery changes in the database
 * ... learner state changes ... PAL identifies the appropriate learning need
 * ... pedagogy engine produces a decision ... actual content is selected ...
 * student completes the next activity ... new result is stored ... mastery
 * changes again."
 *
 * This is deliberately ONE test walking the whole chain with a DB assertion
 * after every step, rather than N isolated unit tests -- the spec's own bar
 * for "PAL should not be marked complete" is that this exact chain has been
 * demonstrated working against real data, not that its pieces pass in
 * isolation. Each individual mechanism (auth, write path, misconception
 * detection/resolution, pedagogy->content, no-data reporting) also has its
 * own focused regression test elsewhere in this directory; this test's job
 * is only to prove they compose into the actual loop.
 */
class PalStudentJourneyEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    public function test_full_student_journey_wrong_answer_to_resolved_misconception_and_updated_mastery(): void
    {
        // ── Fixture: a real institute, student, subject, concept, chapter ──
        $subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'E2E Journey Test School',
            'ShortCode' => 'E2E' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Journey',
            'last_name' => 'Student',
            'sub_institute_id' => $subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'E2E Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $conceptId = (int) DB::table('pal_concepts')->insertGetId([
            'name' => 'E2E Concept ' . random_int(1000, 9999),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $questionId = (int) DB::table('lms_question_master')->insertGetId([
            'subject_id' => $subjectId,
            'concept_id' => $conceptId,
            'question_title' => 'E2E test question',
            'sub_institute_id' => $subInstituteId,
            'status' => 1,
        ]);

        $correctAnswerId = (int) DB::table('answer_master')->insertGetId([
            'question_id' => $questionId,
            'answer' => 'Correct option',
            'correct_answer' => 1,
        ]);
        $wrongAnswerId = (int) DB::table('answer_master')->insertGetId([
            'question_id' => $questionId,
            'answer' => 'Wrong option',
            'correct_answer' => 0,
        ]);

        $session = [
            'user_id' => $studentId,
            'sub_institute_id' => $subInstituteId,
            'syear' => '2026',
            'user_profile_id' => 1,
            'user_profile_name' => 'Super Admin',
        ];
        $basePayload = [
            'grade_id' => 1,
            'standard_id' => 1,
            'subject_id' => $subjectId,
            'chapter_id' => 1,
            'paper_name' => 'E2E Journey Test',
            'questionpaper_time' => 10,
            'total_marks' => 1,
            'total_question' => 1,
            'question_ids' => [$questionId],
            'attempt_time' => [$questionId => 12],
        ];

        // ── Step 1: student starts with no learner state -- must read as
        //    "no data", never a fabricated 0% mastery. ──────────────────────
        $stateBefore = app(LearnerStateEngine::class)->inferCompetency($studentId);
        $this->assertFalse($stateBefore['has_data'], 'Step 1: learner should start with no PAL data at all.');

        // ── Step 2: student answers the question WRONG. ─────────────────────
        $this->withSession($session)->post('/lms/pal', $basePayload + [
            'answer_single' => [$questionId => $wrongAnswerId . '##0'],
        ]);

        // ── Step 3: a real assessment result was stored. ─────────────────────
        $result = DB::table('pal_assessment_results')
            ->where('learner_id', $studentId)->where('question_id', $questionId)->first();
        $this->assertNotNull($result, 'Step 3: pal_assessment_results must contain the real submission.');
        $this->assertSame(0, (int) $result->is_correct);

        // ── Step 4: mastery changed in the database (BKT wrote a real row). ──
        $competencyAfterWrong = DB::table('pal_competencies')
            ->where('learner_id', $studentId)->where('subject_id', $subjectId)->first();
        $this->assertNotNull($competencyAfterWrong, 'Step 4: pal_competencies must now have a row.');
        $masteryAfterWrong = (float) $competencyAfterWrong->mastery_score;

        // ── Step 5: learner state changed -- has_data flips true, and the
        //    mastery_score it reports matches what was actually persisted. ───
        $stateAfterWrong = app(LearnerStateEngine::class)->inferCompetency($studentId);
        $this->assertTrue($stateAfterWrong['has_data'], 'Step 5: learner state must now reflect real data.');
        $this->assertSame($masteryAfterWrong, $stateAfterWrong['mastery_score']);

        // ── Step 6: PAL identified the learning need -- an active
        //    misconception exists for this concept. ─────────────────────────
        $misconception = DB::table('pal_learner_misconceptions')
            ->where('learner_id', $studentId)->where('concept_id', $conceptId)->first();
        $this->assertNotNull($misconception, 'Step 6: a wrong answer must produce a stored misconception.');
        $this->assertSame('active', $misconception->status);

        // ── Step 7 + 8: pedagogy engine produces a decision AND real content
        //    is attached to it in the same response (not just a strategy
        //    name) -- the caller can hand this straight to the student. ──────
        $recommendation = app(PedagogyOrchestrationService::class)->getRecommendation(
            $studentId,
            $conceptId,
            ['sub_institute_id' => $subInstituteId]
        );
        $this->assertArrayHasKey('recommended_pedagogy', $recommendation, 'Step 7: a pedagogy decision must be produced.');
        $this->assertArrayHasKey('content_recommendation', $recommendation, 'Step 8: real content must be attached to the decision.');
        $this->assertTrue(
            array_key_exists('content', $recommendation['content_recommendation'])
                || array_key_exists('legacy_fallback', $recommendation['content_recommendation']),
            'Step 8: content_recommendation must be the real content-pipeline shape, not a placeholder.'
        );

        // ── Step 9/10: student completes the next activity -- a REASSESSMENT,
        //    answered correctly this time. ───────────────────────────────────
        $this->withSession($session)->post('/lms/pal', $basePayload + [
            'answer_single' => [$questionId => $correctAnswerId . '##1'],
        ]);

        // ── Step 11: a new result was stored (two rows now, not overwritten). ─
        $resultCount = DB::table('pal_assessment_results')
            ->where('learner_id', $studentId)->where('question_id', $questionId)->count();
        $this->assertSame(2, $resultCount, 'Step 11: the reassessment must be a new stored result, not a mutation of the first.');

        // ── Step 12: mastery changed again, and moved the right direction --
        //    a correct answer must raise mastery above where the wrong answer
        //    left it. ───────────────────────────────────────────────────────
        $competencyAfterCorrect = DB::table('pal_competencies')
            ->where('learner_id', $studentId)->where('subject_id', $subjectId)->first();
        $masteryAfterCorrect = (float) $competencyAfterCorrect->mastery_score;
        $this->assertGreaterThan(
            $masteryAfterWrong,
            $masteryAfterCorrect,
            'Step 12: a correct reassessment must move mastery up from where the wrong answer left it.'
        );
        $this->assertSame('improving', $competencyAfterCorrect->proficiency_trend);

        // ── Step 13: the misconception is resolved, not left stale. ──────────
        $misconceptionAfter = DB::table('pal_learner_misconceptions')
            ->where('learner_id', $studentId)->where('concept_id', $conceptId)->first();
        $this->assertSame('resolved', $misconceptionAfter->status, 'Step 13: correct reassessment must resolve the misconception.');

        // ── Step 14: learner state reflects the final, updated mastery. ──────
        $stateFinal = app(LearnerStateEngine::class)->inferCompetency($studentId);
        $this->assertTrue($stateFinal['has_data']);
        $this->assertSame($masteryAfterCorrect, $stateFinal['mastery_score']);
    }
}
