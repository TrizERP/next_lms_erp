<?php

namespace App\CareerIntelligence;

use App\CareerIntelligence\Evidence\EvidenceEvent;
use App\CareerIntelligence\Ingestion\ErpSubjectEnrolmentAdapter;
use App\CareerIntelligence\Ingestion\SubjectEnrolmentAdapter;
use App\Models\lms\counselling\StudentAspiration;

/**
 * Phase-1 Career Intelligence UI backing service — `studentCareerEvidence`.
 *
 * Unlike CaiCoreService::evaluate() (which is scoped to ONE occupation's
 * essential subjects, and only resolves at all for the occupations seeded in
 * database/neo4j/cai/occupations/), this service surfaces the student's FULL
 * evidence picture across every subject they have evidence_events for, plus
 * every subject they're actually enrolled in — because the Phase-1 UI is
 * evidence-first, not recommendation-first: it must show what evidence
 * exists and what's missing even when there's no seeded career graph to
 * compare against (which, per the Phase-1 rollout, is true for nearly every
 * occupation today — only 'OCC-ARCHITECT' is seeded).
 *
 * CaiCoreService is still consulted (not duplicated): when the student's
 * aspiration DOES resolve to a seeded occupation, its already-computed
 * `break_point.missing_subjects` (subjects the occupation requires that the
 * student isn't even enrolled in — a stronger, career-specific claim than
 * plain "no evidence yet") is folded into this endpoint's missing_subjects
 * list rather than re-deriving it from the graph a second time.
 */
class CareerEvidenceService
{
    /** database/migrations/2026_08_26_000002_create_evidence_events_table.php source_type enum, human labels only — never invents a source that didn't write the row. */
    private const SOURCE_LABELS = [
        'assessment' => 'Assessment',
        'pal' => 'Adaptive practice',
        'exam' => 'Exam',
        'lms' => 'LMS activity',
        'teacher_diary' => 'Teacher diary',
        'project' => 'Project',
        'activity' => 'Activity',
        'competition' => 'Competition',
        'sports' => 'Sports',
        'certificate' => 'Certificate',
        'reflection' => 'Reflection',
        'psychometric' => 'Psychometric assessment',
        'riasec' => 'Interest profile',
        'counsellor' => 'Counsellor observation',
        'parent' => 'Parent input',
        'portfolio' => 'Portfolio',
        'attendance' => 'Attendance',
    ];

    /** How many recent evidence_events rows the timeline section returns. */
    private const TIMELINE_LIMIT = 100;

    public function __construct(
        private readonly CaiCoreService $caiCoreService,
        // Same default-instance pattern as CaiCoreService's own constructor —
        // SubjectEnrolmentAdapter has no container binding registered, so an
        // unqualified interface type-hint would fail to resolve via app().
        private readonly SubjectEnrolmentAdapter $subjectAdapter = new ErpSubjectEnrolmentAdapter(),
    ) {
    }

    /**
     * @return array{
     *   student_id: string,
     *   aspiration: array|null,
     *   evidence_summary: array,
     *   evidence_events: array,
     *   evidence_status: string,
     *   missing_subjects: string[],
     *   insufficient_data_reason?: string,
     * }
     */
    public function build(string $studentId, string $academicYear): array
    {
        $aspiration = StudentAspiration::where('student_id', $studentId)
            ->where('is_current', true)
            ->orderByDesc('captured_at')
            ->first();

        // Latest non-contested row per (competency, source_type) — the same
        // "current claims only" filter CaiCoreService::buildEvidenceSummary()
        // applies, just not scoped to one occupation's required subjects.
        $currentEvidence = EvidenceEvent::where('student_id', $studentId)
            ->where('contested', false)
            ->orderByDesc('observed_at')
            ->get()
            ->unique(fn (EvidenceEvent $event) => $event->competency_id.'|'.$event->source_type);

        $evidenceSummary = $this->buildEvidenceSummary($currentEvidence);

        $timeline = EvidenceEvent::where('student_id', $studentId)
            ->where('contested', false)
            ->orderByDesc('observed_at')
            ->limit(self::TIMELINE_LIMIT)
            ->get();

        $plan = $this->subjectAdapter->fetch($studentId, $academicYear);

        [$missingSubjects, $evidenceStatus, $insufficientReason] = $this->buildCoverage(
            $plan->resolved,
            $plan->unresolvedReason,
            $plan->subjects,
            $evidenceSummary,
        );

        // Fold in CaiCoreService's own missing_subjects when the aspiration
        // resolves to a seeded occupation — a career-specific claim
        // ("this occupation requires Biology and you're not even enrolled in
        // it"), not something this service re-derives from the graph itself.
        if ($aspiration && $plan->resolved) {
            $alignment = $this->caiCoreService->evaluate($studentId, $academicYear);
            $breakPointMissing = $alignment['break_point']['missing_subjects'] ?? [];
            foreach ($breakPointMissing as $subject) {
                if (! in_array($subject, $missingSubjects, true)) {
                    $missingSubjects[] = $subject;
                }
            }
        }

        $result = [
            'student_id' => $studentId,
            'aspiration' => $aspiration,
            'evidence_summary' => array_values($evidenceSummary),
            'evidence_events' => $timeline->map(fn (EvidenceEvent $event) => $this->mapEvent($event))->values()->all(),
            'evidence_status' => $evidenceStatus,
            'missing_subjects' => $missingSubjects,
        ];

        if ($insufficientReason !== null) {
            $result['insufficient_data_reason'] = $insufficientReason;
        }

        return $result;
    }

    /**
     * Keyed by (competency_id, grade) — the same evidence uniqueness key
     * AssessmentEvidenceAdapter::supersede() now scopes by — so a subject
     * evidenced at two different grades (e.g. SCIENCE at grade 6 and grade
     * 10) surfaces as two distinct entries rather than one conflated with
     * the other, matching CanonicalSubject's own grade-banded vocabulary.
     *
     * @return array<string, array{subject: string, grade: int, level: string, strength: float, observed_at: string|null, source_count: int, sources: array}>
     */
    private function buildEvidenceSummary($currentEvidence): array
    {
        $bySubjectGrade = $currentEvidence->groupBy(
            fn (EvidenceEvent $e) => $e->competency_id.'|'.$e->grade
        );

        $summary = [];
        foreach ($bySubjectGrade as $key => $rows) {
            // Within a subject+grade, prefer the most recently observed row's
            // level as the headline (rows here are already deduped per
            // source_type, so more than one row means more than one distinct
            // source).
            $latest = $rows->sortByDesc('observed_at')->first();

            $summary[$key] = [
                'subject' => $latest->competency_id,
                'grade' => $latest->grade,
                'level' => $latest->performance_level,
                'strength' => (float) $latest->strength,
                'observed_at' => optional($latest->observed_at)->toIso8601String(),
                'source_count' => $rows->count(),
                'sources' => $rows->map(fn (EvidenceEvent $e) => [
                    'source_type' => $e->source_type,
                    'source_label' => self::SOURCE_LABELS[$e->source_type] ?? ucfirst(str_replace('_', ' ', $e->source_type)),
                ])->unique('source_type')->values()->all(),
            ];
        }

        return $summary;
    }

    private function mapEvent(EvidenceEvent $event): array
    {
        return [
            'id' => $event->evidence_id,
            'event_date' => optional($event->observed_at)->toIso8601String(),
            'subject' => $event->competency_id,
            'grade' => $event->grade,
            'level' => $event->performance_level,
            'source' => [
                'source_type' => $event->source_type,
                'source_label' => self::SOURCE_LABELS[$event->source_type] ?? ucfirst(str_replace('_', ' ', $event->source_type)),
            ],
        ];
    }

    /**
     * @param array<string, array> $evidenceSummary keyed by subject, from buildEvidenceSummary()
     * @return array{0: string[], 1: string, 2: string|null} [missing_subjects, evidence_status, insufficient_data_reason]
     */
    private function buildCoverage(bool $planResolved, ?string $unresolvedReason, array $enrolledSubjects, array $evidenceSummary): array
    {
        if (! $planResolved) {
            // Cannot even determine what the student is enrolled in — never
            // guess a subject list, so missing_subjects stays empty rather
            // than being fabricated from evidence_summary alone.
            return [[], 'insufficient', $unresolvedReason];
        }

        // evidenceSummary is now keyed by "SUBJECT|grade", not plain subject —
        // extract the actual subject codes to diff against enrolment.
        $evidencedSubjects = array_unique(array_column($evidenceSummary, 'subject'));
        $missingSubjects = array_values(array_diff($enrolledSubjects, $evidencedSubjects));

        if (empty($evidenceSummary)) {
            return [$missingSubjects, 'no_evidence', null];
        }

        if (empty($missingSubjects)) {
            return [$missingSubjects, 'complete', null];
        }

        return [$missingSubjects, 'partial', null];
    }
}
