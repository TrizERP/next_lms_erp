<?php

namespace App\CareerIntelligence\Evidence;

use App\CareerIntelligence\Ingestion\ErpSubjectEnrolmentAdapter;
use App\CareerIntelligence\Ingestion\ErpSubjectNormaliser;
use App\CareerIntelligence\Ingestion\SubjectEnrolmentAdapter;
use App\CareerIntelligence\Ingestion\SubjectNormaliser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * First (and, this pass, only) evidence_events ingestion adapter — reads
 * real per-question assessment results and rolls them up to SUBJECT-level
 * evidence, per the CI Phase 6 gap analysis:
 *
 *   Student -> lms_online_exam_answer -> lms_question_master (chapter_id) ->
 *   subject -> [ErpSubjectNormaliser, reused from Phase 2] -> CanonicalSubject
 *
 * Deliberately does NOT touch lms_concept / concept-level data at all — the
 * rollup goes straight from question to chapter to subject.
 *
 * competency_id = a Phase 2 CanonicalSubject code (e.g. 'MATHEMATICS'), NOT
 * a fine-grained KASBA-classified competency — no such governed vocabulary
 * exists anywhere in this ERP (confirmed by inspection: the `competency`
 * table is an unrelated 3-row HR/Talent-Management scaffold; Neo4j's
 * :CompetencyStandards nodes are orphaned/stale). Reusing the real, already-
 * governed Subject vocabulary is a deliberate narrowing, not an invention.
 *
 * Adapter law (evidence_events migration doc comment): a pure correctness
 * signal may never write a BEHAVIOUR/ATTITUDE claim. This adapter therefore
 * always writes kasba_dimension = 'KNOWLEDGE' — never inferred as anything
 * else — and that is hard-coded, not configurable per call.
 */
class AssessmentEvidenceAdapter implements EvidenceIngestionAdapter
{
    /**
     * Below this many answered questions in a subject, a bucketed
     * performance_level would be noise, not evidence — skip the subject
     * entirely rather than assert a level from too small a sample.
     */
    private const MIN_ATTEMPTS = 5;

    private const ADAPTER_VERSION = '1.0.0';

    public function __construct(
        private readonly SubjectNormaliser $normaliser = new ErpSubjectNormaliser(),
        private readonly SubjectEnrolmentAdapter $enrolmentAdapter = new ErpSubjectEnrolmentAdapter(),
    ) {
    }

    public function ingest(string $studentId, string $academicYear): array
    {
        // Reused from Phase 2 purely for its grade resolution (real
        // standard.name parsing) — subject-mapping success/failure there is
        // irrelevant to whether we can stamp evidence rows with a grade.
        $plan = $this->enrolmentAdapter->fetch($studentId, $academicYear);
        if ($plan->grade === 0) {
            return []; // no class enrolment to stamp evidence with — nothing to ingest
        }

        $answers = DB::table('lms_online_exam_answer as a')
            ->join('lms_question_master as q', 'q.id', '=', 'a.question_id')
            ->join('subject as sub', 'sub.id', '=', 'q.subject_id')
            ->where('a.student_id', $studentId)
            ->whereIn('a.ans_status', ['right', 'wrong']) // the only real values in this ERP; see adapter report
            ->select('sub.subject_name', 'q.chapter_id', 'a.ans_status', 'a.created_at', 'a.question_paper_id')
            ->orderBy('a.created_at')
            ->get();

        if ($answers->isEmpty()) {
            return [];
        }

        $bySubject = [];
        foreach ($answers as $row) {
            $competencyId = $this->normaliser->toCanonical($row->subject_name);
            if ($competencyId === null) {
                continue; // unmapped subject label — never guess which subject this is
            }
            $bySubject[$competencyId]['answers'][] = $row;
            $bySubject[$competencyId]['chapters'][$row->chapter_id][] = $row->ans_status === 'right';
        }

        $written = [];
        foreach ($bySubject as $competencyId => $data) {
            $evidenceId = $this->writeSubjectEvidence($studentId, $academicYear, $plan->grade, $competencyId, $data);
            if ($evidenceId !== null) {
                $written[] = $evidenceId;
            }
        }

        return $written;
    }

    private function writeSubjectEvidence(
        string $studentId,
        string $academicYear,
        int $grade,
        string $competencyId,
        array $data
    ): ?int {
        $answers = $data['answers'];
        $attempts = count($answers);

        if ($attempts < self::MIN_ATTEMPTS) {
            return null;
        }

        $correct = count(array_filter($answers, fn ($a) => $a->ans_status === 'right'));
        $ratio = $correct / $attempts;
        $lastAnswer = end($answers);
        $lastAttemptAt = Carbon::parse($lastAnswer->created_at);

        // Evidence uniqueness key is (student_id, competency_id, grade) — see
        // supersede(). If the current active row for this exact key already
        // reflects an answer at least as recent as this run's latest answer,
        // nothing new happened for this subject+grade since last time: skip
        // writing a no-op version rather than churning every qualifying
        // subject on every submission (most of which had no new activity).
        $currentActive = EvidenceEvent::where('student_id', $studentId)
            ->where('competency_id', $competencyId)
            ->where('grade', $grade)
            ->where('source_type', 'assessment')
            ->where('contested', false)
            ->first();

        if ($currentActive && $currentActive->observed_at !== null
            && $currentActive->observed_at->gte($lastAttemptAt)) {
            return $currentActive->evidence_id;
        }

        $chapterRatios = array_map(
            fn (array $flags) => count(array_filter($flags)) / count($flags),
            $data['chapters']
        );

        $event = EvidenceEvent::create([
            'student_id' => $studentId,
            'academic_year' => $academicYear,
            'grade' => $grade,
            'source_type' => 'assessment',
            'source_id' => (string) $lastAnswer->question_paper_id,
            'competency_id' => $competencyId,
            'kasba_dimension' => 'KNOWLEDGE',
            'performance_level' => $this->bucket($ratio),
            'strength' => $this->computeStrength($lastAttemptAt, $attempts, $chapterRatios),
            'reliability' => $this->computeReliability($attempts),
            'validity_scope' => "Correctness on curriculum-aligned {$competencyId} assessment questions — "
                . 'reflects content knowledge only, not behaviour or real-world application.',
            'observed_at' => $lastAttemptAt,
            'assessment_id' => (string) $lastAnswer->question_paper_id,
            'verified' => true, // assessment source — auto-true per the migration's own rule
            'contested' => false,
            'provenance' => [
                'method' => 'assessment_correctness_subject_rollup',
                'adapter_version' => self::ADAPTER_VERSION,
                'ingested_by' => 'AssessmentEvidenceAdapter',
                'attempts_considered' => $attempts,
                'chapters_considered' => count($data['chapters']),
                'signals_used' => ['recency', 'volume', 'cross_chapter_consistency'],
                // No difficulty/DOK column exists anywhere in this ERP (confirmed
                // by inspection) — omitted, never faked with a placeholder value.
                'signals_omitted' => ['difficulty', 'dok'],
            ],
        ]);

        // Supersede AFTER creation — the new row's id is only known post-insert
        // with an auto-increment PK (unlike the previous pre-generated UUID
        // design, where the id could be written into the prior row's
        // superseded_by before the new row existed).
        $this->supersede($studentId, $competencyId, $grade, $event->evidence_id);

        return $event->evidence_id;
    }

    /**
     * Evidence uniqueness key: (student_id, competency_id, grade). Scoping by
     * grade (not just subject) means a student's evidence lineage restarts
     * per grade rather than being superseded across a grade change — e.g. a
     * grade-6 SCIENCE row and a grade-10 SCIENCE row can both stay active
     * simultaneously, since CanonicalSubject's own vocabulary already
     * documents SCIENCE as spanning different grade-banded curriculum
     * content, not one continuous subject.
     */
    private function supersede(string $studentId, string $competencyId, int $grade, int $newEvidenceId): void
    {
        EvidenceEvent::where('student_id', $studentId)
            ->where('competency_id', $competencyId)
            ->where('grade', $grade)
            ->where('source_type', 'assessment')
            ->where('contested', false)
            // The new row itself now exists (unlike the old pre-generated-UUID
            // ordering) and would otherwise match this same query — exclude it
            // so it never marks itself contested/superseded.
            ->where('evidence_id', '!=', $newEvidenceId)
            ->update(['contested' => true, 'superseded_by' => $newEvidenceId]);
    }

    private function bucket(float $ratio): string
    {
        return match (true) {
            $ratio >= 0.75 => 'demonstrated',
            $ratio >= 0.50 => 'developing',
            $ratio >= 0.25 => 'emerging',
            default => 'insufficient',
        };
    }

    /**
     * f(recency, volume, cross-chapter consistency) — difficulty/DOK terms
     * from the migration's own comment are omitted, not faked, since no such
     * data exists anywhere in this ERP (confirmed by inspection).
     */
    private function computeStrength(Carbon $lastAttemptAt, int $attempts, array $chapterRatios): float
    {
        $daysSince = $lastAttemptAt->diffInDays(Carbon::now());
        $recency = match (true) {
            $daysSince <= 30 => 1.0,
            $daysSince <= 90 => 0.7,
            $daysSince <= 180 => 0.4,
            default => 0.2,
        };

        $volume = min($attempts / 30, 1.0);

        $consistency = 1.0;
        if (count($chapterRatios) > 1) {
            $mean = array_sum($chapterRatios) / count($chapterRatios);
            $variance = array_sum(array_map(fn ($r) => ($r - $mean) ** 2, $chapterRatios)) / count($chapterRatios);
            $consistency = max(0.0, 1 - min($variance * 2, 1.0));
        }

        return round(($recency + $volume + $consistency) / 3, 2);
    }

    /** Purely a function of evidence volume — independent of how well the student did. */
    private function computeReliability(int $attempts): float
    {
        return round(min($attempts / 50, 1.0), 2);
    }
}
