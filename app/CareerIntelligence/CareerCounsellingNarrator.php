<?php

namespace App\CareerIntelligence;

use App\Domain\AI\Support\ModelClient;
use App\Exceptions\GraphUnavailableException;
use App\Services\PAL\Coherence\CoherenceMapRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The Career Counselling narrative — "an AI counsellor retrieving the
 * student's skill/interest graph", grounded in what actually exists rather
 * than what the original brief assumed exists.
 *
 * A prior pass confirmed `HasInterest`/`HasWorkValue` edges do not exist
 * anywhere in the live graph, and the RIASEC labels this ERP does hold
 * (`onet_interests`, surfaced by `lmsCounsellingController::intrestResults`)
 * belong to an unrelated, non-graph O*NET Interest Profiler pipeline, scored
 * entirely client-side with no Neo4j relationship behind it at all. So
 * "the student's skill/interest graph" is reground here in the two real
 * graph-backed facts this estate actually has about one student:
 *
 *   1. `CaiCoreService::evaluate()` — the CAI-CORE O*NET/stream/subject
 *      career graph (database/neo4j/cai), same call `CareerQuestNarrator`
 *      and `CareerAlignmentNarrator` make.
 *   2. The student's real concept-mastery state in the Set Coherence Map
 *      (database/neo4j — :Concept nodes, `pal_concept_mastery` evidence),
 *      read the same way `GraphRagRetriever`/`CoherenceMapController::explain()`
 *      already do for mastery data — via `CoherenceMapRepository`, not by
 *      touching `GraphRagRetriever` itself (that class answers "why is this
 *      ONE concept blocked"; this narrator needs the whole-subject shape
 *      instead, so it composes `CoherenceMapRepository::readiness()`
 *      directly, exactly as `CoherenceMapController::learner()` does).
 *
 * Both are independent graph reads (unlike `CareerAlignmentNarrator`, which
 * narrates a single already-computed payload), so this class owns both
 * calls itself, mirroring `CareerQuestNarrator`'s shape exactly. Four
 * distinct statuses, matching explain()'s contract: a graph/Neo4j failure
 * from either read returns `graph_unavailable`; an LLM failure returns
 * `model_unavailable`; genuinely insufficient evidence (no resolvable
 * aspiration AND no evidenced concept in this student's class) is relayed as
 * `insufficient_evidence` with an honest reason, never a fabricated
 * narrative.
 */
class CareerCounsellingNarrator
{
    public function __construct(
        private readonly CaiCoreService $cai,
        private readonly CoherenceMapRepository $coherenceMap,
        private readonly ModelClient $modelClient,
    ) {
    }

    /**
     * @return array{status:string, narrative:?string, message:?string, alignment:?array, mastery:?array}
     */
    public function narrate(int $studentId, int|string|null $subInstituteId, string $academicYear): array
    {
        try {
            $alignment = $this->cai->evaluate((string) $studentId, $academicYear);
        } catch (GraphUnavailableException|Throwable $exception) {
            return $this->result('graph_unavailable', null,
                'The career knowledge graph is unavailable right now, so a narrative could not be generated.');
        }

        try {
            $mastery = $this->conceptMasterySnapshot($studentId);
        } catch (GraphUnavailableException|Throwable $exception) {
            return $this->result('graph_unavailable', null,
                'The concept-mastery graph is unavailable right now, so a narrative could not be generated.');
        }

        $alignmentKnown = ($alignment['alignment_status'] ?? 'INSUFFICIENT_DATA') !== 'INSUFFICIENT_DATA';
        $masteryKnown = $mastery !== null && $mastery['evidenced_count'] > 0;

        if (! $alignmentKnown && ! $masteryKnown) {
            return $this->result('insufficient_evidence', null,
                'There is not enough evidence yet — from either a stated career aspiration or recorded '
                . 'concept practice — to write an honest counselling narrative.');
        }

        $client = $this->modelClient->forInstitute($subInstituteId);

        if (! $client->isConfigured()) {
            return $this->result('model_unavailable', null, 'No AI model is configured for this institute.');
        }

        try {
            // Same generous ceiling as the other narrators in this pass, for the
            // same measured reason (the configured model spends output-token
            // budget on internal reasoning before any visible text).
            $narrative = $client->chat(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($alignment, $mastery)],
                ],
                model: null,
                maxTokens: 2048,
                temperature: 0.3,
            );
        } catch (Throwable $exception) {
            return $this->result('model_unavailable', null, 'The narrative could not be generated.');
        }

        $narrative = trim((string) $narrative);

        if ($narrative === '') {
            return $this->result('model_unavailable', null, 'The narrative could not be generated.');
        }

        return $this->result('ok', $narrative, null) + ['alignment' => $alignment, 'mastery' => $mastery];
    }

    /**
     * @return array{status:string, narrative:?string, message:?string, alignment:?array, mastery:?array}
     */
    private function result(string $status, ?string $narrative, ?string $message): array
    {
        return ['status' => $status, 'narrative' => $narrative, 'message' => $message, 'alignment' => null, 'mastery' => null];
    }

    /**
     * The student's real concept-mastery shape for whichever subject their
     * actual evidence lives in.
     *
     * Deliberately NOT the "richest-mapped subject" heuristic
     * `CoherenceMapController::scopeForLearner()` uses (picking whichever
     * subject has the most AUTHORED concepts) — that heuristic answers "what
     * is most useful to show on a cold first load", not "where is this
     * student's real evidence". Measured live: for one real test student
     * (97926, standard 43) the richest-authored subject (4512, 266 concepts)
     * has zero of their mastery rows, while their actual 8 evidenced
     * concepts all sit under a different subject (3975, 241 concepts) that
     * the richest-map heuristic would never have picked. Picking the
     * evidenced subject first avoids reporting "no evidence" on a student
     * who has real evidence, just filed under a less-authored subject.
     *
     * @return array{subject_id:int, subject_name:string, total_concepts:int, mastered:int, ready:int, blocked:int, percent_mastered:float, evidenced_count:int, weakest_evidenced_concepts:array<int, array{concept_id:int, name:string, mastery:float, attempts:int}>}|null
     */
    private function conceptMasterySnapshot(int $studentId): ?array
    {
        $enrollment = DB::table('tblstudent_enrollment')
            ->where('student_id', $studentId)
            ->orderByDesc('syear')
            ->orderByDesc('id')
            ->first(['standard_id', 'sub_institute_id']);

        if ($enrollment === null) {
            return null;
        }

        $tenant = (int) $enrollment->sub_institute_id;
        $standardId = (int) $enrollment->standard_id;

        $subjectId = DB::table('pal_concept_mastery as m')
            ->join('lms_concept as c', 'c.id', '=', 'm.concept_ref_id')
            ->where('m.learner_id', $studentId)
            ->where('m.attempts', '>', 0)
            ->where('c.standard_id', $standardId)
            ->groupBy('c.subject_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('c.subject_id');

        if ($subjectId === null) {
            // No evidenced subject at all for this student/class — fall back to
            // the richest-mapped subject purely so a real subject name can still
            // be named; evidenced_count below will correctly read 0 either way,
            // never fabricated.
            $subjectId = DB::table('lms_concept')
                ->where('sub_institute_id', $tenant)
                ->where('standard_id', $standardId)
                ->groupBy('subject_id')
                ->orderByRaw('COUNT(*) DESC')
                ->value('subject_id');
        }

        if ($subjectId === null) {
            return null;
        }

        $subjectId = (int) $subjectId;

        $readiness = $this->coherenceMap->readiness($standardId, $subjectId, $studentId, $tenant);

        if ($readiness === []) {
            return null;
        }

        $conceptIds = array_keys($readiness);

        $evidenced = DB::table('pal_concept_mastery')
            ->where('learner_id', $studentId)
            ->whereIn('concept_ref_id', $conceptIds)
            ->where('attempts', '>', 0)
            ->orderBy('p_mastery')
            ->get(['concept_ref_id', 'p_mastery', 'attempts']);

        $weakest = [];

        if ($evidenced->isNotEmpty()) {
            $topFive = $evidenced->take(5);
            $names = DB::table('lms_concept')
                ->whereIn('id', $topFive->pluck('concept_ref_id'))
                ->pluck('name', 'id');

            foreach ($topFive as $row) {
                $weakest[] = [
                    'concept_id' => (int) $row->concept_ref_id,
                    'name' => (string) ($names[$row->concept_ref_id] ?? "Concept {$row->concept_ref_id}"),
                    'mastery' => round((float) $row->p_mastery, 4),
                    'attempts' => (int) $row->attempts,
                ];
            }
        }

        $subjectName = DB::table('subject')->where('id', $subjectId)->value('subject_name');

        $counts = ['mastered' => 0, 'ready' => 0, 'blocked' => 0];

        foreach ($readiness as $c) {
            $counts[$c['state']] = ($counts[$c['state']] ?? 0) + 1;
        }

        $total = count($readiness);

        return [
            'subject_id' => $subjectId,
            'subject_name' => (string) ($subjectName ?? "Subject {$subjectId}"),
            'total_concepts' => $total,
            'mastered' => $counts['mastered'],
            'ready' => $counts['ready'],
            'blocked' => $counts['blocked'],
            'percent_mastered' => $total > 0 ? round(($counts['mastered'] / $total) * 100, 1) : 0.0,
            'evidenced_count' => $evidenced->count(),
            'weakest_evidenced_concepts' => $weakest,
        ];
    }

    private function systemPrompt(): string
    {
        return 'You are an AI career counsellor writing a short, supportive narrative for a student. Use '
            . 'only the facts given in the message — never invent a career match, an interest or work-value '
            . 'score, a concept name, or a mastery figure that is not present in the input. If one of the two '
            . 'evidence sources (career alignment or concept mastery) is marked unavailable below, say plainly '
            . 'that it is not ready yet rather than guessing. Keep the answer to 4-6 sentences of plain, '
            . 'encouraging language, addressed directly to the student.';
    }

    /**
     * @param  array<string, mixed>  $alignment
     * @param  array<string, mixed>|null  $mastery
     */
    private function userPrompt(array $alignment, ?array $mastery): string
    {
        $lines = [];

        $ambition = $alignment['stated_ambition'] ?? null;

        if (($alignment['alignment_status'] ?? 'INSUFFICIENT_DATA') === 'INSUFFICIENT_DATA') {
            $lines[] = 'Career alignment evidence: not available yet'
                . (! empty($alignment['insufficient_data_reason']) ? ' (' . $alignment['insufficient_data_reason'] . ')' : '')
                . '.';
        } else {
            if (is_array($ambition) && ! empty($ambition['occupation_name'])) {
                $lines[] = sprintf('Stated career aspiration: %s.', $ambition['occupation_name']);
            }

            $lines[] = sprintf('Career alignment check result: %s.', $alignment['alignment_status']);

            $breakPoint = $alignment['break_point'] ?? null;

            if (is_array($breakPoint) && ! empty($breakPoint['missing_subjects']) && is_array($breakPoint['missing_subjects'])) {
                $lines[] = 'Required subject(s) not currently enrolled in: '
                    . implode(', ', $breakPoint['missing_subjects']) . '.';
            }
        }

        if ($mastery === null || $mastery['evidenced_count'] === 0) {
            $lines[] = 'Concept-mastery evidence: not available yet — no recorded practice attempts.';
        } else {
            $lines[] = sprintf(
                'Concept mastery in %s: %d of %d concepts mastered (%s%%), based on %d evidenced concept(s).',
                $mastery['subject_name'],
                $mastery['mastered'],
                $mastery['total_concepts'],
                $mastery['percent_mastered'],
                $mastery['evidenced_count']
            );

            if ($mastery['weakest_evidenced_concepts'] !== []) {
                $lines[] = 'Weakest evidenced concepts, lowest mastery first:';

                foreach ($mastery['weakest_evidenced_concepts'] as $concept) {
                    $lines[] = sprintf('- "%s" (mastery %.2f, %d attempt(s))', $concept['name'], $concept['mastery'], $concept['attempts']);
                }
            }
        }

        $lines[] = 'Write a short, honest, encouraging counselling narrative for the student, grounded only '
            . 'in the facts above.';

        return implode("\n", $lines);
    }
}
