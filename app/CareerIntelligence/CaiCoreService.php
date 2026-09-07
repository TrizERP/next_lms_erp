<?php

namespace App\CareerIntelligence;

use App\CareerIntelligence\Evidence\EvidenceEvent;
use App\CareerIntelligence\Ingestion\ErpSubjectEnrolmentAdapter;
use App\CareerIntelligence\Ingestion\SubjectEnrolmentAdapter;
use App\Models\lms\counselling\StudentAspiration;
use App\Services\Neo4jService;
use DateTimeInterface;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Date;

/**
 * CI-GUIDE-DEV-001 Group C. Computes ALIGNED / MISALIGNED / INSUFFICIENT_DATA
 * for one student against one career pathway graph (database/neo4j/cai).
 *
 * The rule this class exists to enforce: it NEVER returns a confident
 * ALIGNED/MISALIGNED answer on top of missing or unresolved input. Every
 * early return below is INSUFFICIENT_DATA, never a guessed default.
 */
class CaiCoreService
{
    /**
     * Governed mapping from the O*NET onetsoc_code stored on
     * student_aspirations.occupation_id (Phase 1 reuses the existing O*NET
     * occupation dropdown) to this wedge's own graph occupation_id
     * (cai_core.cypher's own convention, e.g. 'OCC-ARCHITECT' — a small,
     * curated vocabulary, NOT the full O*NET catalog).
     *
     * Populated ONLY for occupations actually seeded in
     * database/neo4j/cai/occupations/ with real sourced facts. An aspiration
     * naming an occupation not in this map means the pathway graph has
     * nothing to compare against — that is INSUFFICIENT_DATA, never a guess
     * (e.g. never fuzzy-matched by occupation_name).
     */
    private const OCCUPATION_MAP = [
        '17-1011.00' => 'OCC-ARCHITECT', // O*NET: "Architects, Except Landscape and Naval"
    ];

    /**
     * The wedge is explicitly CBSE-scoped (CI-GUIDE-DEV-001's own framing).
     * No `board` column exists anywhere in this ERP's schema (checked
     * school_setup and standard) to derive it from a specific student, so
     * this is a deliberate, documented scope constant rather than an
     * invented lookup.
     */
    private const BOARD = 'CBSE';

    /**
     * Verbatim from the supplied cai_core.cypher, section "3. CAI-CORE
     * QUERY" — with the trailing `;` removed (a bare RETURN is the correct
     * terminator for a single Bolt statement) and MERGE seeding logic
     * excluded (that lives in database/neo4j/cai/occupations/, run once by
     * cai:seed-graph, not per request).
     */
    private const CAI_CORE_QUERY = <<<'CYPHER'
        MATCH (o:Occupation {occupation_id:$occupation_id})

        // required stream (may be null for occupations with no stream constraint)
        OPTIONAL MATCH (o)-[:REQUIRES_STREAM]->(reqStream:Stream)

        // essential subjects for this occupation
        OPTIONAL MATCH (o)-[rq:REQUIRES_SUBJECT {essentiality:'essential'}]->(reqSub:Subject)
        WITH o, reqStream,
             collect(DISTINCT reqSub.code) AS requiredSubjects

        // exams (informational for the counsellor)
        OPTIONAL MATCH (o)-[:REQUIRES_EXAM]->(reqExam:Exam)
        WITH o, reqStream, requiredSubjects,
             collect(DISTINCT {exam_id:reqExam.exam_id, name:reqExam.name}) AS requiredExams

        // deadline policy for this board+grade+year
        OPTIONAL MATCH (pol:StreamPolicy {board:$board, grade:$grade, academic_year:$academic_year})

        // ---- compute the two structural break conditions ----
        WITH o, reqStream, requiredSubjects, requiredExams, pol,
             // stream mismatch: occupation needs a stream and the student is not in it
             (reqStream IS NOT NULL AND reqStream.code <> $current_stream) AS streamMismatch,
             // missing essential subjects the student is not enrolled in
             [s IN requiredSubjects WHERE NOT s IN $student_subjects] AS missingSubjects

        WITH o, reqStream, requiredExams, pol, streamMismatch, missingSubjects,
             // assemble misalignment codes
             [c IN [
                CASE WHEN streamMismatch                     THEN 'ERR_STREAM_MISMATCH' END,
                CASE WHEN size(missingSubjects) > 0          THEN 'ERR_MISSING_PREREQ'  END
             ] WHERE c IS NOT NULL] AS codes

        RETURN
          o.occupation_id AS occupation_id,
          CASE WHEN size(codes) = 0 THEN 'ALIGNED' ELSE 'MISALIGNED' END AS alignment_status,
          codes AS misalignment_codes,
          {
            current_stream:  $current_stream,
            required_stream: coalesce(reqStream.code, null),
            missing_subjects: missingSubjects,
            required_exams:   requiredExams,
            deadline_date:    coalesce(toString(pol.change_deadline), null),
            days_remaining:   CASE WHEN pol.change_deadline IS NULL THEN null
                                   ELSE duration.inDays($today, pol.change_deadline).days END
          } AS break_point
        CYPHER;

    /** Small, separate query — deliberately not folded into CAI_CORE_QUERY, which stays verbatim per cai_core.cypher. */
    private const REQUIRED_SUBJECTS_QUERY = <<<'CYPHER'
        MATCH (o:Occupation {occupation_id:$occupation_id})-[:REQUIRES_SUBJECT {essentiality:'essential'}]->(s:Subject)
        RETURN collect(DISTINCT s.code) AS codes
        CYPHER;

    public function __construct(
        private readonly Neo4jService $neo4j,
        private readonly SubjectEnrolmentAdapter $subjectAdapter = new ErpSubjectEnrolmentAdapter(),
    ) {
    }

    /**
     * @return array the CAI payload (CI-SPEC-CAI-001 shape, assembled from
     *               cai_core.cypher's own RETURN clause plus the fields
     *               CI-SPEC-CONSOLE-001 documents the console binding to —
     *               see the Phase 3 report for how this was derived; the
     *               canonical CI-SPEC-CAI-001 document was not available)
     */
    public function evaluate(string $studentId, string $academicYear, ?DateTimeInterface $today = null): array
    {
        $today ??= new \DateTimeImmutable();

        $aspiration = StudentAspiration::where('student_id', $studentId)
            ->where('is_current', true)
            ->orderByDesc('captured_at')
            ->first();

        if (! $aspiration) {
            return $this->insufficientData($studentId, null, 'No current aspiration on file for this student.');
        }

        $occupationId = $aspiration->occupation_id
            ? (self::OCCUPATION_MAP[$aspiration->occupation_id] ?? null)
            : null;

        if ($occupationId === null) {
            return $this->insufficientData(
                $studentId,
                $aspiration,
                'This occupation is not yet covered by the career pathway map.'
            );
        }

        $plan = $this->subjectAdapter->fetch($studentId, $academicYear);

        if (! $plan->resolved) {
            return $this->insufficientData(
                $studentId,
                $aspiration,
                $plan->unresolvedReason ?? 'Could not determine the student\'s current subjects.'
            );
        }

        $row = $this->neo4j->run(self::CAI_CORE_QUERY, [
            'occupation_id' => $occupationId,
            'current_stream' => $plan->stream,
            'student_subjects' => $plan->subjects,
            'board' => self::BOARD,
            'grade' => $plan->grade,
            'academic_year' => $academicYear,
            'today' => $this->toNeo4jDate($today),
        ])->first();

        if (! $row) {
            // occupation_id validated against OCCUPATION_MAP above, so this
            // means the graph node itself is missing (seed not run / removed)
            return $this->insufficientData(
                $studentId,
                $aspiration,
                'The career pathway map has no data for this occupation.'
            );
        }

        $misalignmentCodes = $this->toPlain($row->get('misalignment_codes'));

        if ($this->hasParentConflict($aspiration)) {
            $misalignmentCodes[] = 'ERR_PARENT_CONFLICT';
        }

        return [
            'student_id' => $studentId,
            'alignment_status' => empty($misalignmentCodes) ? 'ALIGNED' : 'MISALIGNED',
            'misalignment_codes' => $misalignmentCodes,
            'stated_ambition' => [
                'occupation_id' => $aspiration->occupation_id,
                'occupation_name' => $aspiration->occupation_name ?? $aspiration->expectation_age_30,
                'certainty_score' => $aspiration->certainty,
            ],
            'break_point' => $this->toPlain($row->get('break_point')),
            'evidence_summary' => $this->buildEvidenceSummary($occupationId, $studentId),
        ];
    }

    /**
     * Phase 6: real evidence_events data for the competencies this
     * occupation actually requires — never fabricated. Returns null (stays
     * absent from the payload) when there's simply no evidence yet; the
     * console's existing "insufficient evidence yet" fallback handles that,
     * per CI-SPEC-CONSOLE-001.
     *
     * competency_id here is a Phase 2 CanonicalSubject code (e.g.
     * 'MATHEMATICS'), not a fine-grained KASBA competency — see the Phase 6
     * gap analysis for why (no such vocabulary exists anywhere in this ERP).
     */
    private function buildEvidenceSummary(string $occupationId, string $studentId): ?array
    {
        $requiredCodes = $this->toPlain(
            $this->neo4j->run(self::REQUIRED_SUBJECTS_QUERY, ['occupation_id' => $occupationId])
                ->first()
                ?->get('codes')
        );

        if (empty($requiredCodes)) {
            return null;
        }

        $currentEvidence = EvidenceEvent::where('student_id', $studentId)
            ->whereIn('competency_id', $requiredCodes)
            ->where('contested', false)
            ->orderByDesc('observed_at')
            ->get()
            ->unique('competency_id'); // one (the latest) row per competency

        if ($currentEvidence->isEmpty()) {
            return null;
        }

        $competencies = $currentEvidence->map(fn (EvidenceEvent $e) => [
            'competency_id' => $e->competency_id,
            'performance_level' => $e->performance_level,
        ])->values()->all();

        $averageReliability = $currentEvidence->avg('reliability');
        $confidence = match (true) {
            $averageReliability >= 0.7 => 'High',
            $averageReliability >= 0.4 => 'Medium',
            default => 'Low',
        };

        return [
            'competencies' => $competencies,
            'evidence_confidence' => $confidence,
        ];
    }

    private function hasParentConflict(StudentAspiration $aspiration): bool
    {
        if (empty($aspiration->parent_occupation_id) && empty($aspiration->parent_occupation_name)) {
            return false; // no parent aspiration on file — not a conflict, just absent
        }

        if (! empty($aspiration->occupation_id) && ! empty($aspiration->parent_occupation_id)) {
            return $aspiration->occupation_id !== $aspiration->parent_occupation_id;
        }

        // At least one side is free-text only (no occupation_id) — fall back
        // to the same display label `stated_ambition.occupation_name` uses,
        // and compare verbatim (case-insensitive), never a fuzzy match. If
        // either side genuinely has no label to compare, that is NOT treated
        // as a conflict — a false ERR_PARENT_CONFLICT is its own kind of
        // confident lie (a family disagreement that may not exist).
        $studentLabel = trim((string) ($aspiration->occupation_name ?? $aspiration->expectation_age_30));
        $parentLabel = trim((string) ($aspiration->parent_occupation_name ?? $aspiration->parent_occupation_id));

        if ($studentLabel === '' || $parentLabel === '') {
            return false;
        }

        return strcasecmp($studentLabel, $parentLabel) !== 0;
    }

    private function insufficientData(string $studentId, ?StudentAspiration $aspiration, string $reason): array
    {
        return [
            'student_id' => $studentId,
            'alignment_status' => 'INSUFFICIENT_DATA',
            'misalignment_codes' => [],
            'stated_ambition' => $aspiration ? [
                'occupation_id' => $aspiration->occupation_id,
                'occupation_name' => $aspiration->occupation_name ?? $aspiration->expectation_age_30,
                'certainty_score' => $aspiration->certainty,
            ] : null,
            'break_point' => null,
            'evidence_summary' => null,
            'insufficient_data_reason' => $reason,
        ];
    }

    private function toNeo4jDate(DateTimeInterface $date): Date
    {
        $midnightUtc = (new \DateTimeImmutable($date->format('Y-m-d'), new \DateTimeZone('UTC')));

        return new Date(intdiv($midnightUtc->getTimestamp(), 86400));
    }

    /** Recursively unwrap Laudis CypherList/CypherMap into plain PHP arrays. */
    private function toPlain(mixed $value): mixed
    {
        if ($value instanceof CypherList) {
            return array_map(fn ($item) => $this->toPlain($item), $value->toArray());
        }

        if ($value instanceof CypherMap) {
            return array_map(fn ($item) => $this->toPlain($item), $value->toArray());
        }

        return $value;
    }
}
