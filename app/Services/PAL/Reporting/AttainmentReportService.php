<?php

namespace App\Services\PAL\Reporting;

use App\Models\Eso\LearnerNodeState;
use App\Services\Eso\EsoPolicyService;
use Illuminate\Support\Facades\DB;

/**
 * Curriculum Coverage and Student Attainment — two reports, deliberately not
 * one number.
 *
 * Coverage answers "was it taught?" — how much of the curriculum has material
 * a student could actually be taught from. Attainment answers "did they
 * demonstrate it?" — how much of what was taught students have actually shown
 * mastery of, from evidence rather than from delivery.
 *
 * They are separated because the gap between them is the finding. "92% of the
 * curriculum is covered" and "66% of it has been demonstrated" is a different,
 * more actionable statement than any single blended score, and it is the one
 * that tells a principal WHERE to intervene rather than merely how much was
 * shipped.
 *
 * Read-only. Writes nothing and resolves no decisions.
 */
class AttainmentReportService
{
    public function __construct(
        private readonly EsoPolicyService $policy
    ) {
    }

    /**
     * Coverage and attainment for one cohort — a standard, in one academic
     * year, optionally narrowed to a subject.
     *
     * @return array<string, mixed>
     */
    public function forCohort(
        int $subInstituteId,
        int $standardId,
        string $syear,
        ?int $subjectId = null
    ): array {
        $studentIds = $this->cohort($subInstituteId, $standardId, $syear);
        $chapters = $this->chapters($subInstituteId, $standardId, $subjectId);

        if ($chapters->isEmpty()) {
            return $this->empty($standardId, $subjectId, $syear, count($studentIds));
        }

        $chapterIds = $chapters->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Every concept in the curriculum for this scope — the denominator for
        // coverage. Not just the ESO-ready ones: a concept nobody can be
        // taught still counts against coverage, which is the entire point of
        // measuring it.
        $allConcepts = DB::table('lms_concept')
            ->whereIn('chapter_id', $chapterIds)
            ->where('sub_institute_id', $subInstituteId)
            ->get(['id', 'name', 'chapter_id']);

        $readyConceptIds = array_flip(
            $this->policy->esoReadyConceptsForChapters($chapterIds, $subInstituteId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );

        $masteryByConcept = $studentIds === []
            ? []
            : $this->masteredCountsByConcept($studentIds, array_keys($readyConceptIds));

        $evidenceByConcept = $studentIds === []
            ? []
            : $this->attemptedCountsByConcept($studentIds, array_keys($readyConceptIds));

        $chapterNames = $chapters->pluck('chapter_name', 'id');
        $rows = [];

        foreach ($allConcepts as $concept) {
            $conceptId = (int) $concept->id;
            $taught = isset($readyConceptIds[$conceptId]);
            $mastered = $masteryByConcept[$conceptId] ?? 0;
            $attempted = $evidenceByConcept[$conceptId] ?? 0;

            $rows[] = [
                'concept_id' => $conceptId,
                'name' => $concept->name,
                'chapter_id' => (int) $concept->chapter_id,
                'chapter_name' => $chapterNames[$concept->chapter_id] ?? null,
                // Coverage is a property of the curriculum, not of a student.
                'taught' => $taught,
                'students_attempted' => $attempted,
                'students_mastered' => $mastered,
                // Null, not 0, when the concept cannot be taught: nobody has
                // failed a concept that was never available to them, and a 0%
                // here would read as a teaching failure rather than a content gap.
                'attainment_pct' => $taught && $studentIds !== []
                    ? $this->pct($mastered, count($studentIds))
                    : null,
            ];
        }

        $taughtRows = array_values(array_filter($rows, fn (array $r) => $r['taught']));

        return [
            'scope' => [
                'standard_id' => $standardId,
                'subject_id' => $subjectId,
                'syear' => $syear,
                'student_count' => count($studentIds),
            ],

            // Report 1 — was it taught?
            'coverage' => [
                'concepts_total' => count($rows),
                'concepts_taught' => count($taughtRows),
                'coverage_pct' => $this->pct(count($taughtRows), count($rows)),
                'chapters_total' => count($chapterIds),
            ],

            // Report 2 — did students demonstrate it?
            //
            // The denominator is TAUGHT concepts, not all of them. Measuring
            // attainment against material that was never delivered blames a
            // school for a content gap, and hides the gap by folding it into a
            // teaching number.
            'attainment' => [
                'concepts_measured' => count($taughtRows),
                'concepts_with_any_evidence' => count(array_filter($taughtRows, fn (array $r) => $r['students_attempted'] > 0)),
                'mean_attainment_pct' => $this->mean(array_column($taughtRows, 'attainment_pct')),
                // Taught, but not one student has attempted it. Distinct from
                // low attainment: nothing has been measured at all here.
                'taught_but_unevidenced' => array_values(array_map(
                    fn (array $r) => ['concept_id' => $r['concept_id'], 'name' => $r['name']],
                    array_filter($taughtRows, fn (array $r) => $r['students_attempted'] === 0)
                )),
            ],

            'concepts' => $rows,
        ];
    }

    /** Students enrolled in this standard for this year. */
    private function cohort(int $subInstituteId, int $standardId, string $syear): array
    {
        return DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('syear', $syear)
            ->whereNull('end_date')
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function chapters(int $subInstituteId, int $standardId, ?int $subjectId)
    {
        return DB::table('chapter_master')
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->when($subjectId !== null, fn ($q) => $q->where('subject_id', $subjectId))
            ->orderBy('sort_order')
            ->get(['id', 'chapter_name', 'subject_id']);
    }

    /**
     * How many of these students have mastered each concept.
     *
     * Reads the mastery status EsoPolicyService::masteryVerdict() itself
     * writes: when a concept's verdict comes out mastered, every one of its
     * nodes is swept to STATUS_MASTERED (and later STATUS_RETAINED through the
     * retention ladder). So this is not a third definition of mastery
     * competing with the K/A threshold rule — it is that rule's recorded
     * outcome, read in bulk.
     *
     * The limitation that follows, and it is a real one: a student who now
     * QUALIFIES for mastery but whose verdict has not been resolved since
     * their last attempt will not appear here until it is. This report is
     * therefore a floor, never an overstatement — which is the safe direction
     * for a number a school acts on.
     *
     * Only K and A nodes gate mastery (S/transfer nodes deliberately do not),
     * matching masteryVerdict().
     *
     * @param  array<int, int>  $studentIds
     * @param  array<int, int>  $conceptIds
     * @return array<int, int> concept_id => student count
     */
    private function masteredCountsByConcept(array $studentIds, array $conceptIds): array
    {
        if ($conceptIds === []) {
            return [];
        }

        $gatingNodes = DB::table('pal_concept_nodes')
            ->whereIn('concept_id', $conceptIds)
            ->whereIn('node_type', ['K', 'A'])
            ->get(['id', 'concept_id']);

        if ($gatingNodes->isEmpty()) {
            return [];
        }

        $requiredPerConcept = $gatingNodes->groupBy('concept_id')->map->count();
        $conceptForNode = $gatingNodes->pluck('concept_id', 'id');

        $masteredStates = LearnerNodeState::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('node_id', $gatingNodes->pluck('id'))
            ->whereIn('status', [LearnerNodeState::STATUS_MASTERED, LearnerNodeState::STATUS_RETAINED])
            ->get(['student_id', 'node_id']);

        // student => concept => how many gating nodes are mastered
        $perStudent = [];
        foreach ($masteredStates as $state) {
            $conceptId = (int) ($conceptForNode[$state->node_id] ?? 0);
            if ($conceptId === 0) {
                continue;
            }
            $perStudent[$state->student_id][$conceptId] = ($perStudent[$state->student_id][$conceptId] ?? 0) + 1;
        }

        $counts = [];
        foreach ($perStudent as $conceptCounts) {
            foreach ($conceptCounts as $conceptId => $masteredNodes) {
                // Every gating node, not merely one of them.
                if ($masteredNodes >= (int) $requiredPerConcept->get($conceptId, PHP_INT_MAX)) {
                    $counts[$conceptId] = ($counts[$conceptId] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    /**
     * How many of these students have any recorded response on each concept.
     *
     * Separates "taught, and they are struggling" from "taught, and nobody has
     * touched it" — two situations a school responds to completely differently.
     *
     * @param  array<int, int>  $studentIds
     * @param  array<int, int>  $conceptIds
     * @return array<int, int> concept_id => student count
     */
    private function attemptedCountsByConcept(array $studentIds, array $conceptIds): array
    {
        if ($conceptIds === []) {
            return [];
        }

        return DB::table('eso_response_log')
            ->whereIn('student_id', $studentIds)
            ->whereIn('concept_id', $conceptIds)
            ->select('concept_id', DB::raw('COUNT(DISTINCT student_id) as students'))
            ->groupBy('concept_id')
            ->pluck('students', 'concept_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** @param array<int, float|null> $values */
    private function mean(array $values): ?float
    {
        $present = array_values(array_filter($values, fn ($v) => $v !== null));

        return $present === [] ? null : round(array_sum($present) / count($present), 1);
    }

    private function pct(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round(($part / $whole) * 100, 1);
    }

    /** @return array<string, mixed> */
    private function empty(int $standardId, ?int $subjectId, string $syear, int $studentCount): array
    {
        return [
            'scope' => [
                'standard_id' => $standardId,
                'subject_id' => $subjectId,
                'syear' => $syear,
                'student_count' => $studentCount,
            ],
            'coverage' => ['concepts_total' => 0, 'concepts_taught' => 0, 'coverage_pct' => 0.0, 'chapters_total' => 0],
            'attainment' => [
                'concepts_measured' => 0,
                'concepts_with_any_evidence' => 0,
                'mean_attainment_pct' => null,
                'taught_but_unevidenced' => [],
            ],
            'concepts' => [],
        ];
    }
}
