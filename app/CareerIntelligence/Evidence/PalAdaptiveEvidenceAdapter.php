<?php

namespace App\CareerIntelligence\Evidence;

use App\CareerIntelligence\Ingestion\ErpSubjectEnrolmentAdapter;
use App\CareerIntelligence\Ingestion\ErpSubjectNormaliser;
use App\CareerIntelligence\Ingestion\SubjectEnrolmentAdapter;
use App\CareerIntelligence\Ingestion\SubjectNormaliser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills evidence_events' `source_type = 'pal'` slot with real Adaptive
 * Learning Engine outcomes.
 *
 * That slot was not new — CareerEvidenceService::SOURCE_LABELS has advertised
 * 'pal' => 'Adaptive practice' since Career Intelligence shipped — but no
 * adapter ever wrote it, so it was an empty enum value. Everything a student
 * did inside ESO was invisible to their career evidence.
 *
 * Deliberately mirrors AssessmentEvidenceAdapter rather than inventing a
 * second set of rules: same subject-level rollup through the already-governed
 * CanonicalSubject vocabulary, same MIN_ATTEMPTS floor, same supersede chain
 * keyed on (student_id, competency_id, grade), same verified/contested
 * semantics. The ONLY differences are the source it reads and the claim it is
 * entitled to make.
 *
 * ---------------------------------------------------------------------------
 * ADAPTER LAW: WHAT THIS SOURCE MAY AND MAY NOT ASSERT
 * ---------------------------------------------------------------------------
 * ESO measures correctness against curriculum concept nodes. That is a
 * knowledge signal and nothing more, so kasba_dimension is hard-coded to
 * 'KNOWLEDGE' — never inferred, never configurable per call — exactly as the
 * evidence_events migration's own adapter law requires of a correctness
 * source. Adaptive practice being personalised does NOT license a BEHAVIOUR or
 * ATTITUDE claim (persistence, resilience, curiosity), however tempting the
 * retry data makes it: nothing in the engine distinguishes a persistent
 * student from one whose browser was left open.
 */
class PalAdaptiveEvidenceAdapter implements EvidenceIngestionAdapter
{
    /**
     * Below this many concepts mastered in a subject, a bucketed
     * performance_level would be noise. Lower than the assessment adapter's
     * question-count floor because the unit here is a whole mastered concept
     * (each backed by its own multi-response D4 verdict), not a single answer.
     */
    private const MIN_CONCEPTS = 2;

    private const ADAPTER_VERSION = '1.0.0';

    public function __construct(
        private readonly SubjectNormaliser $normaliser = new ErpSubjectNormaliser(),
        private readonly SubjectEnrolmentAdapter $enrolmentAdapter = new ErpSubjectEnrolmentAdapter(),
    ) {
    }

    /**
     * @return int[] evidence_id of every row written this run
     */
    public function ingest(string $studentId, string $academicYear): array
    {
        if (! Schema::hasTable('eso_decision_log')) {
            return [];
        }

        $plan = $this->enrolmentAdapter->fetch($studentId, $academicYear);
        if ($plan->grade === 0) {
            return []; // no class enrolment to stamp evidence with
        }

        // The engine's own record of concepts that reached a D4 mastery
        // verdict, and of spaced-retrieval checks those concepts later
        // survived. Both are ESO decisions, already logged — this reads them,
        // it does not recompute mastery.
        $outcomes = DB::table('eso_decision_log as d')
            ->join('lms_concept as c', 'c.id', '=', 'd.concept_id')
            ->join('subject as s', 's.id', '=', 'c.subject_id')
            ->where('d.student_id', $studentId)
            ->whereIn('d.action', ['mastered_stop_practice', 'retained'])
            ->select('s.subject_name', 'd.concept_id', 'd.action', 'd.created_at')
            ->orderBy('d.created_at')
            ->get();

        if ($outcomes->isEmpty()) {
            return [];
        }

        $bySubject = [];
        foreach ($outcomes as $row) {
            $competencyId = $this->normaliser->toCanonical($row->subject_name);
            if ($competencyId === null) {
                continue; // unmapped subject label — never guess which subject this is
            }

            $bySubject[$competencyId]['mastered'][(int) $row->concept_id] = true;
            if ($row->action === 'retained') {
                $bySubject[$competencyId]['retained'][(int) $row->concept_id] = true;
            }
            $bySubject[$competencyId]['last_at'] = $row->created_at;
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
        $mastered = count($data['mastered'] ?? []);
        if ($mastered < self::MIN_CONCEPTS) {
            return null;
        }

        $retained = count($data['retained'] ?? []);
        $lastAt = Carbon::parse($data['last_at']);

        // Same no-op idempotency check as the assessment adapter: if the
        // active row already reflects an outcome at least as recent as this
        // run's latest, nothing new happened — do not churn a fresh version.
        $currentActive = EvidenceEvent::where('student_id', $studentId)
            ->where('competency_id', $competencyId)
            ->where('grade', $grade)
            ->where('source_type', 'pal')
            ->where('contested', false)
            ->first();

        if ($currentActive && $currentActive->observed_at !== null && $currentActive->observed_at->gte($lastAt)) {
            return $currentActive->evidence_id;
        }

        $event = EvidenceEvent::create([
            'student_id' => $studentId,
            'academic_year' => $academicYear,
            'grade' => $grade,
            'source_type' => 'pal',
            'source_id' => (string) array_key_first($data['mastered']),
            'competency_id' => $competencyId,
            'kasba_dimension' => 'KNOWLEDGE', // adapter law — see the class docblock
            'performance_level' => $this->bucket($mastered, $retained),
            'strength' => $this->computeStrength($lastAt, $mastered, $retained),
            'reliability' => $this->computeReliability($mastered),
            'validity_scope' => "Concepts mastered through adaptive practice in {$competencyId}, verified against "
                . 'the engine\'s knowledge and application thresholds — reflects content knowledge only, '
                . 'not behaviour, attitude or real-world application.',
            'observed_at' => $lastAt,
            'verified' => true, // engine-adjudicated correctness, same standing as an assessment
            'contested' => false,
            'provenance' => [
                'method' => 'eso_concept_mastery_subject_rollup',
                'adapter_version' => self::ADAPTER_VERSION,
                'ingested_by' => 'PalAdaptiveEvidenceAdapter',
                'concepts_mastered' => $mastered,
                'concepts_retained' => $retained,
                'signals_used' => ['recency', 'mastery_volume', 'spaced_retention'],
                // ESO's own difficulty ordering is inert wherever
                // pal_question_metadata.difficulty_1_to_5 is unpopulated, which
                // is the norm today — omitted rather than faked.
                'signals_omitted' => ['difficulty', 'dok'],
            ],
        ]);

        $this->supersede($studentId, $competencyId, $grade, $event->evidence_id);

        return $event->evidence_id;
    }

    /** @see AssessmentEvidenceAdapter::supersede() — identical key and semantics. */
    private function supersede(string $studentId, string $competencyId, int $grade, int $newEvidenceId): void
    {
        EvidenceEvent::where('student_id', $studentId)
            ->where('competency_id', $competencyId)
            ->where('grade', $grade)
            ->where('source_type', 'pal')
            ->where('contested', false)
            ->where('evidence_id', '!=', $newEvidenceId)
            ->update(['contested' => true, 'superseded_by' => $newEvidenceId]);
    }

    /**
     * Mastering a concept is already a threshold verdict, so volume alone
     * cannot separate 'demonstrated' from 'developing' — what distinguishes
     * them is whether the mastery SURVIVED a spaced check days or weeks later.
     * A concept mastered and then retained is durable knowledge; one mastered
     * yesterday and never retested is not yet.
     */
    private function bucket(int $mastered, int $retained): string
    {
        $retentionRate = $mastered > 0 ? $retained / $mastered : 0.0;

        return match (true) {
            $mastered >= 5 && $retentionRate >= 0.5 => 'demonstrated',
            $retained > 0 => 'developing',
            $mastered >= 3 => 'developing',
            default => 'emerging',
        };
    }

    private function computeStrength(Carbon $lastAt, int $mastered, int $retained): float
    {
        $daysSince = $lastAt->diffInDays(Carbon::now());
        $recency = match (true) {
            $daysSince <= 30 => 1.0,
            $daysSince <= 90 => 0.7,
            $daysSince <= 180 => 0.4,
            default => 0.2,
        };

        $volume = min($mastered / 10, 1.0);
        $durability = $mastered > 0 ? min($retained / $mastered, 1.0) : 0.0;

        return round(($recency + $volume + $durability) / 3, 2);
    }

    /** Purely a function of evidence volume — independent of how well the student did. */
    private function computeReliability(int $mastered): float
    {
        return round(min($mastered / 15, 1.0), 2);
    }
}
