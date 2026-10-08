<?php

namespace App\Services\Curriculum;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EXPECTED -> PLANNED -> DELIVERED -> ASSESSED -> ACHIEVED -> GAP for one
 * curriculum, for the teacher-facing Curriculum Outcomes page.
 *
 * ---------------------------------------------------------------------------
 * WHY ACHIEVEMENT HAS TWO TIERS
 * ---------------------------------------------------------------------------
 * True concept-level PAL mastery (`pal_concept_mastery`, reached via
 * `lms_concept_outcome`) only has real evidence for a handful of chapters
 * estate-wide - most question banks tag `chapter_id` but not `concept_id`.
 * So every outcome's Achievement is computed at TWO tiers and the richer one
 * is surfaced without hiding the broader one:
 *   - exam_based   : avg(pal_diagnostic_attempt.percentage) at chapter grain.
 *                    Broadly available, the default.
 *   - pal_verified : pooled pal_concept_mastery pass-rate over this specific
 *                    outcome's mapped concepts. Rare, richer, concept grain.
 * Neither tier is scoped by current roster/enrollment: a concept-mastery row
 * from a student now enrolled in a different standard is still real evidence
 * of that concept, and a diagnostic attempt from a seed/test student id that
 * never got an enrollment row is still a real recorded percentage. Silently
 * dropping either on an enrollment join would show "Data not available"
 * where real evidence exists - exactly what this feature must not do.
 *
 * Owns no table and writes nothing - every value is derived on read, same as
 * CurriculumPlanningApiController and MasteryOverviewService.
 */
class OutcomeAnalyticsService
{
    /** Achievement below this is a learning gap. A product call, not a data fact - kept as one named constant so it is trivial to retune. */
    private const LEARNING_GAP_THRESHOLD = 50.0;

    public function __construct(
        private ChapterDeliveryStatus $deliveryStatus = new ChapterDeliveryStatus(),
    ) {
    }

    /**
     * KPI cards, health status, the full Learning Outcome Tracker and the
     * three gap buckets, for one curriculum.
     */
    public function summary(array $filters): array
    {
        $rows = $this->buildRows($filters);

        $chapterMapped = $rows->filter(fn ($r) => $r['chapter_id'] !== null);
        $expectedChapterMapped = $chapterMapped->count();

        $deliveredCount = $chapterMapped->where('delivered', 'delivered')->count();
        $assessedCount = $chapterMapped->where('assessed', 'assessed')->count();
        $resourcedCount = $chapterMapped->where('has_resources', true)->count();
        $achievedCount = $chapterMapped
            ->filter(fn ($r) => $r['achievement']['value'] !== null && $r['achievement']['value'] >= self::LEARNING_GAP_THRESHOLD)
            ->count();
        $tierCounts = $chapterMapped->countBy(fn ($r) => $r['achievement']['tier']);

        $kpis = [
            'expected_outcomes' => [
                'total' => $rows->count(),
                'chapter_mapped' => $expectedChapterMapped,
                'curriculum_level' => $rows->count() - $expectedChapterMapped,
            ],
            'delivered' => [
                'count' => $deliveredCount,
                'percent' => $this->pct($deliveredCount, $expectedChapterMapped),
            ],
            'achieved' => [
                'count' => $achievedCount,
                'percent' => $this->pct($achievedCount, $expectedChapterMapped),
                'tier_breakdown' => [
                    'exam_based' => $tierCounts->get('exam_based', 0),
                    'pal_verified' => $tierCounts->get('pal_verified', 0),
                    'unavailable' => $tierCounts->get('unavailable', 0),
                ],
            ],
            'delivery_gap' => [
                'count' => $expectedChapterMapped - $deliveredCount,
            ],
            'resource_coverage' => [
                'percent' => $this->pct($resourcedCount, $expectedChapterMapped),
            ],
            'assessment_coverage' => [
                'percent' => $this->pct($assessedCount, $expectedChapterMapped),
            ],
        ];

        $trackerRows = $rows->map(fn ($r) => [
            'outcome_id' => $r['outcome_id'],
            'code' => $r['code'],
            'description' => $r['description'],
            'type' => $r['type'],
            'chapter_id' => $r['chapter_id'],
            'chapter_name' => $r['chapter_name'],
            'delivered' => $r['delivered'],
            'assessed' => $r['assessed'],
            'achievement' => $r['achievement'],
            'gap_category' => $r['gap_category'],
        ])->values();

        $chapterIds = $rows->pluck('chapter_id')->filter()->unique()->values()->all();
        $outcomeIds = $rows->pluck('outcome_id')->all();
        $conceptMapAll = $this->outcomeConceptMap($outcomeIds);
        $allConceptIds = $conceptMapAll->flatten(1)->pluck('concept_id')->unique()->values()->all();

        return [
            'health_status' => $this->healthStatus($expectedChapterMapped, $deliveredCount, $kpis['delivery_gap']['count']),
            'kpis' => $kpis,
            'tracker_rows' => $trackerRows,
            'lo_rows' => $this->buildLoRows($rows),
            'gaps' => [
                'delivery_gaps' => $trackerRows->where('gap_category', 'delivery_gap')->values(),
                'assessment_gaps' => $trackerRows->where('gap_category', 'assessment_gap')->values(),
                'learning_gaps' => $trackerRows->where('gap_category', 'learning_gap')->values(),
            ],
            'resource_breakdown' => $this->resourceBreakdown($chapterIds, (int) $filters['sub_institute_id']),
            'mastery_distribution' => $this->masteryDistribution($allConceptIds, (int) $filters['sub_institute_id']),
            'student_rows' => $this->studentRows($filters, $rows),
        ];
    }

    /**
     * The Outcome Detail drawer payload for one outcome.
     *
     * Returns null when the outcome does not belong to this curriculum (the
     * controller turns that into a 404 - a bare outcome_id must not read
     * across curricula/tenants).
     */
    public function detail(int $outcomeId, array $filters): ?array
    {
        $outcome = DB::table('lms_learning_outcomes')
            ->where('id', $outcomeId)
            ->where('curriculum_id', $filters['curriculum_id'])
            ->first(['id', 'curriculum_id', 'parent_id', 'code', 'type', 'description', 'chapter_id']);

        if ($outcome === null) {
            return null;
        }

        $chapterId = ((int) $outcome->chapter_id) > 0 ? (int) $outcome->chapter_id : null;
        $parent = $outcome->parent_id
            ? DB::table('lms_learning_outcomes')->where('id', $outcome->parent_id)->first(['id', 'code', 'description'])
            : null;

        $conceptMap = $this->outcomeConceptMap([$outcomeId]);
        $mappedConcepts = $conceptMap->get($outcomeId, collect());
        $conceptIds = $mappedConcepts->pluck('concept_id')->unique()->values()->all();
        $conceptNames = $conceptIds
            ? DB::table('lms_concept')->whereIn('id', $conceptIds)->pluck('name', 'id')
            : collect();
        $masteryByConcept = $conceptIds
            ? $this->masteryByConcept($conceptIds, (int) $filters['sub_institute_id'])
            : collect();

        if ($chapterId === null) {
            return [
                'outcome_id' => $outcome->id,
                'code' => $outcome->code,
                'type' => $outcome->type,
                'description' => $outcome->description,
                'parent' => $parent ? ['id' => $parent->id, 'code' => $parent->code, 'description' => $parent->description] : null,
                'chapter' => null,
                'delivery' => null,
                'assessment' => null,
                'resources' => null,
                'achievement' => ['value' => null, 'mastery' => null, 'tier' => 'not_applicable', 'source' => null, 'status' => null, 'secondary' => null],
                'mapped_concepts' => [],
                'gap_category' => 'not_applicable',
                'gap_explanation' => 'This is a curriculum-level goal or competency with no single chapter, so delivery, assessment and achievement are not tracked against it directly.',
                'links' => null,
            ];
        }

        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first(['id', 'chapter_name', 'subject_id', 'standard_id']);

        $delivery = $this->deliveryStatus->forChapters(
            (int) $filters['sub_institute_id'],
            $filters['syear'],
            $filters['standard_id'] ?? null,
            null,
            $filters['term_id'] ?? null,
            [$chapterId]
        )[$chapterId];

        $chapterQuestionCount = DB::table('lms_question_master')
            ->where('chapter_id', $chapterId)
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->whereNull('deleted_at')
            ->count();

        $conceptLevelQuestionCount = $conceptIds
            ? DB::table('lms_question_master')
                ->whereIn('concept_id', $conceptIds)
                ->where('sub_institute_id', $filters['sub_institute_id'])
                ->whereNull('deleted_at')
                ->count()
            : 0;

        $resourceCount = DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->whereNotNull('filename')->where('filename', '<>', '')
            ->count();

        $resourceBreakdown = $this->resourceBreakdown([$chapterId], (int) $filters['sub_institute_id']);

        $exam = $this->examBasedAchievement([$chapterId], $filters)->get($chapterId);
        $pal = $this->palVerifiedAchievement($conceptMap, [(int) $filters['sub_institute_id']])->get($outcomeId);
        $achievement = $this->blendAchievement($exam, $pal);

        $hasAttempts = $exam !== null;
        $assessed = ($chapterQuestionCount > 0 || $hasAttempts) ? 'assessed' : 'not_assessed';
        $delivered = $this->deliveredLabel($delivery['status']);
        $gapCategory = $this->classifyGap($delivered, $assessed, $achievement);

        return [
            'outcome_id' => $outcome->id,
            'code' => $outcome->code,
            'type' => $outcome->type,
            'description' => $outcome->description,
            'parent' => $parent ? ['id' => $parent->id, 'code' => $parent->code, 'description' => $parent->description] : null,
            'chapter' => [
                'chapter_id' => $chapterId,
                'chapter_name' => $chapter->chapter_name ?? null,
                'subject_id' => $chapter->subject_id ?? null,
                'standard_id' => $chapter->standard_id ?? null,
            ],
            'delivery' => $delivery,
            'assessment' => [
                'chapter_question_count' => $chapterQuestionCount,
                'concept_level_question_count' => $conceptLevelQuestionCount,
            ],
            'resources' => ['chapter_resource_count' => $resourceCount, 'breakdown' => $resourceBreakdown],
            'achievement' => $achievement,
            'mapped_concepts' => $mappedConcepts->map(function ($m) use ($conceptNames, $masteryByConcept) {
                $pooled = $masteryByConcept->get($m->concept_id);

                return [
                    'concept_id' => (int) $m->concept_id,
                    'concept_name' => $conceptNames->get($m->concept_id),
                    'match_source' => $m->match_source,
                    'match_score' => $m->match_score !== null ? (float) $m->match_score : null,
                    'pal_mastery_pct' => $pooled && $pooled->n_rows > 0 ? round(($pooled->n_pass / $pooled->n_rows) * 100, 1) : null,
                ];
            })->values(),
            'gap_category' => $gapCategory,
            'gap_explanation' => $this->gapExplanation($gapCategory, $delivered, $assessed, $achievement),
            'links' => [
                'concept_intelligence' => $this->courseMasterLink($chapter, $chapterId, 'concept-intelligence'),
                'coherence_map' => $this->courseMasterLink($chapter, $chapterId, 'coherence-map'),
                'question_bank' => $this->courseMasterLink($chapter, $chapterId, 'question-bank'),
            ],
        ];
    }

    // -------------------------------------------------------------------
    // Row building shared by summary()
    // -------------------------------------------------------------------

    private function buildRows(array $filters): Collection
    {
        $outcomes = DB::table('lms_learning_outcomes')
            ->where('curriculum_id', $filters['curriculum_id'])
            ->orderBy('code')
            ->get(['id', 'parent_id', 'code', 'type', 'description', 'chapter_id']);

        $chapterIds = $outcomes->pluck('chapter_id')->filter(fn ($id) => (int) $id > 0)->unique()->values()->all();
        $outcomeIds = $outcomes->pluck('id')->all();

        $chapterNames = $chapterIds
            ? DB::table('chapter_master')->whereIn('id', $chapterIds)->pluck('chapter_name', 'id')
            : collect();

        $delivery = $this->deliveryStatus->forChapters(
            (int) $filters['sub_institute_id'],
            $filters['syear'],
            $filters['standard_id'] ?? null,
            null,
            $filters['term_id'] ?? null,
            $chapterIds
        );

        $questionCounts = $chapterIds ? $this->questionCountsByChapter($chapterIds, (int) $filters['sub_institute_id']) : collect();
        $resourceCounts = $chapterIds ? $this->resourceCountsByChapter($chapterIds, (int) $filters['sub_institute_id']) : collect();
        $examByChapter = $chapterIds ? $this->examBasedAchievement($chapterIds, $filters) : collect();

        $conceptMap = $this->outcomeConceptMap($outcomeIds);
        $palByOutcome = $this->palVerifiedAchievement($conceptMap, [(int) $filters['sub_institute_id']]);

        return $outcomes->map(function ($o) use ($chapterNames, $delivery, $questionCounts, $resourceCounts, $examByChapter, $palByOutcome) {
            $chapterId = ((int) $o->chapter_id) > 0 ? (int) $o->chapter_id : null;

            if ($chapterId === null) {
                return [
                    'outcome_id' => $o->id, 'parent_id' => $o->parent_id, 'code' => $o->code, 'description' => $o->description, 'type' => $o->type,
                    'chapter_id' => null, 'chapter_name' => null,
                    'delivered' => 'not_applicable', 'assessed' => 'not_applicable', 'has_resources' => false,
                    'achievement' => ['value' => null, 'mastery' => null, 'tier' => 'not_applicable', 'source' => null, 'status' => null, 'secondary' => null],
                    'gap_category' => 'not_applicable',
                ];
            }

            $chapterDelivery = $delivery[$chapterId] ?? ['status' => 'Upcoming'];
            $delivered = $this->deliveredLabel($chapterDelivery['status']);

            $questionCount = (int) ($questionCounts->get($chapterId) ?? 0);
            $hasAttempts = $examByChapter->has($chapterId);
            $assessed = ($questionCount > 0 || $hasAttempts) ? 'assessed' : 'not_assessed';

            $achievement = $this->blendAchievement($examByChapter->get($chapterId), $palByOutcome->get($o->id));
            $gapCategory = $this->classifyGap($delivered, $assessed, $achievement);

            return [
                'outcome_id' => $o->id, 'parent_id' => $o->parent_id, 'code' => $o->code, 'description' => $o->description, 'type' => $o->type,
                'chapter_id' => $chapterId, 'chapter_name' => $chapterNames->get($chapterId),
                'delivered' => $delivered, 'assessed' => $assessed,
                'has_resources' => (int) ($resourceCounts->get($chapterId) ?? 0) > 0,
                'achievement' => $achievement,
                'gap_category' => $gapCategory,
            ];
        });
    }

    // -------------------------------------------------------------------
    // LO / LI nesting, resource mix, mastery distribution, student rollup
    // -------------------------------------------------------------------

    /**
     * Competency rows ("LO") with their leaf learning_outcome children
     * ("LI") nested beneath - a real 3-level hierarchy already present in
     * lms_learning_outcomes (Goal -> Competency -> leaf), confirmed live:
     * a leaf row's parent_id points at its competency's id, not its goal's.
     */
    private function buildLoRows(Collection $rows): Collection
    {
        $competencies = $rows->filter(fn ($r) => $r['type'] === 'competency');

        $asRow = fn ($r) => [
            'outcome_id' => $r['outcome_id'], 'code' => $r['code'], 'description' => $r['description'],
            'chapter_id' => $r['chapter_id'], 'chapter_name' => $r['chapter_name'],
            'delivered' => $r['delivered'], 'assessed' => $r['assessed'],
            'achievement' => $r['achievement'], 'gap_category' => $r['gap_category'],
        ];

        return $competencies->map(function ($lo) use ($rows, $asRow) {
            $indicators = $rows
                ->filter(fn ($r) => $r['parent_id'] !== null && (int) $r['parent_id'] === (int) $lo['outcome_id'])
                ->map($asRow)
                ->values();

            return $asRow($lo) + ['indicators' => $indicators];
        })->values();
    }

    /** @param array<int,int> $chapterIds */
    private function resourceBreakdown(array $chapterIds, int $subInstituteId): array
    {
        if (empty($chapterIds)) {
            return [];
        }

        return DB::table('content_master')
            ->whereIn('chapter_id', $chapterIds)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNotNull('filename')->where('filename', '<>', '')
            ->selectRaw("COALESCE(NULLIF(file_type, ''), 'other') as file_type, count(*) as total")
            ->groupBy('file_type')
            ->pluck('total', 'file_type')
            ->toArray();
    }

    /**
     * Real pal_concept_mastery.band values, as they exist for this
     * curriculum's mapped concepts - not forced into a generic 4-level
     * scale, because the band thresholds are Administration-configurable
     * (BktEngine::band()), not a fixed universal rubric.
     *
     * @param  array<int,int>  $conceptIds
     */
    private function masteryDistribution(array $conceptIds, int $subInstituteId): array
    {
        if (empty($conceptIds)) {
            return [];
        }

        return DB::table('pal_concept_mastery')
            ->whereIn('concept_ref_id', $conceptIds)
            ->where('sub_institute_id', $subInstituteId)
            ->selectRaw("COALESCE(band, 'unbanded') as band, count(*) as total")
            ->groupBy('band')
            ->pluck('total', 'band')
            ->toArray();
    }

    /**
     * Per-student achievement across every chapter-mapped outcome, for
     * whichever students actually have real evidence (PAL mastery or a
     * diagnostic attempt) anywhere in this curriculum.
     *
     * Deliberately NOT built over the whole class roster: real evidence is
     * sparse (often a handful of students per curriculum today), and a
     * dense matrix of mostly-empty rows for 800+ roster students would
     * both cost a very large response and bury the real signal. The
     * `note` field says so explicitly when the result is empty, rather
     * than rendering a quietly blank table.
     */
    private function studentRows(array $filters, Collection $outcomeRows): array
    {
        $chapterMapped = $outcomeRows->filter(fn ($r) => $r['chapter_id'] !== null)->values();

        if ($chapterMapped->isEmpty()) {
            return ['students' => [], 'outcomes' => [], 'note' => 'No chapter-mapped outcomes to score.'];
        }

        $chapterIds = $chapterMapped->pluck('chapter_id')->unique()->values()->all();
        $outcomeIds = $chapterMapped->pluck('outcome_id')->all();
        $conceptMap = $this->outcomeConceptMap($outcomeIds);
        $allConceptIds = $conceptMap->flatten(1)->pluck('concept_id')->unique()->values()->all();

        $masteryByStudentConcept = [];
        if (!empty($allConceptIds)) {
            foreach (
                DB::table('pal_concept_mastery')
                    ->whereIn('concept_ref_id', $allConceptIds)
                    ->where('sub_institute_id', $filters['sub_institute_id'])
                    ->get(['learner_id', 'concept_ref_id', 'p_mastery', 'mastery_gate']) as $row
            ) {
                $masteryByStudentConcept[(int) $row->learner_id][(int) $row->concept_ref_id] = $row;
            }
        }

        $examByStudentChapter = [];
        foreach (
            DB::table('pal_diagnostic_attempt')
                ->whereIn('chapter_id', $chapterIds)
                ->where('sub_institute_id', $filters['sub_institute_id'])
                ->where('syear', $filters['syear'])
                ->where('status', 'submitted')
                ->selectRaw('student_id, chapter_id, avg(percentage) as avg_pct, count(*) as n')
                ->groupBy('student_id', 'chapter_id')
                ->get() as $row
        ) {
            $examByStudentChapter[(int) $row->student_id][(int) $row->chapter_id] = $row;
        }

        $studentIds = array_values(array_unique(array_merge(
            array_keys($masteryByStudentConcept),
            array_keys($examByStudentChapter)
        )));

        if (empty($studentIds)) {
            return ['students' => [], 'outcomes' => [], 'note' => 'No student-level evidence recorded for this curriculum yet.'];
        }

        // Defensive cap, not an expected case: real evidence today is sparse,
        // but a future institute with full PAL coverage must not return an
        // unbounded per-student x per-outcome matrix in one response.
        $studentIds = array_slice($studentIds, 0, 60);

        $names = DB::table('tblstudent')->whereIn('id', $studentIds)->get(['id', 'first_name', 'middle_name', 'last_name'])->keyBy('id');
        $rolls = DB::table('tblstudent_enrollment')
            ->whereIn('student_id', $studentIds)
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->when(!empty($filters['standard_id']), fn ($q) => $q->where('standard_id', $filters['standard_id']))
            ->pluck('roll_no', 'student_id');

        $totalOutcomes = $chapterMapped->count();
        $students = [];

        foreach ($studentIds as $studentId) {
            $scores = [];
            $achievedCount = 0;
            $assessedCount = 0;
            $sum = 0.0;
            $n = 0;

            foreach ($chapterMapped as $outcome) {
                $outcomeId = $outcome['outcome_id'];
                $chapterId = $outcome['chapter_id'];
                $concepts = $conceptMap->get($outcomeId, collect())->pluck('concept_id')->unique();

                $palRows = 0;
                $palPass = 0;
                foreach ($concepts as $conceptId) {
                    $row = $masteryByStudentConcept[$studentId][$conceptId] ?? null;
                    if ($row === null) {
                        continue;
                    }
                    $palRows++;
                    if ((float) $row->p_mastery >= (float) $row->mastery_gate) {
                        $palPass++;
                    }
                }

                $examRow = $examByStudentChapter[$studentId][$chapterId] ?? null;

                if ($palRows > 0) {
                    $value = round(($palPass / $palRows) * 100, 1);
                    $tier = 'pal_verified';
                } elseif ($examRow !== null) {
                    $value = round((float) $examRow->avg_pct, 1);
                    $tier = 'exam_based';
                } else {
                    $value = null;
                    $tier = 'unavailable';
                }

                $scores[$outcomeId] = ['value' => $value, 'tier' => $tier];

                if ($value !== null) {
                    $assessedCount++;
                    $sum += $value;
                    $n++;
                    if ($value >= self::LEARNING_GAP_THRESHOLD) {
                        $achievedCount++;
                    }
                }
            }

            $name = $names->get($studentId);
            $students[] = [
                'student_id' => $studentId,
                'name' => $name
                    ? trim(preg_replace('/\s+/', ' ', "{$name->first_name} {$name->middle_name} {$name->last_name}"))
                    : "Student #{$studentId}",
                'roll_no' => $rolls->get($studentId),
                'scores' => $scores,
                'summary' => [
                    'achieved_count' => $achievedCount,
                    'assessed_count' => $assessedCount,
                    'total_outcomes' => $totalOutcomes,
                    'mean' => $n > 0 ? round($sum / $n, 1) : null,
                ],
            ];
        }

        // Most-evidenced students first - a sparse set should lead with
        // whoever actually has something to show.
        usort($students, fn ($a, $b) => $b['summary']['assessed_count'] - $a['summary']['assessed_count']);

        return [
            'students' => $students,
            'outcomes' => $chapterMapped->map(fn ($o) => ['outcome_id' => $o['outcome_id'], 'code' => $o['code']])->values()->all(),
            'note' => null,
        ];
    }

    // -------------------------------------------------------------------
    // Data sources
    // -------------------------------------------------------------------

    /** @param array<int,int> $chapterIds */
    private function questionCountsByChapter(array $chapterIds, int $subInstituteId): Collection
    {
        return DB::table('lms_question_master')
            ->whereIn('chapter_id', $chapterIds)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->selectRaw('chapter_id, count(*) as total')
            ->groupBy('chapter_id')
            ->pluck('total', 'chapter_id');
    }

    /** @param array<int,int> $chapterIds */
    private function resourceCountsByChapter(array $chapterIds, int $subInstituteId): Collection
    {
        return DB::table('content_master')
            ->whereIn('chapter_id', $chapterIds)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNotNull('filename')->where('filename', '<>', '')
            ->selectRaw('chapter_id, count(*) as total')
            ->groupBy('chapter_id')
            ->pluck('total', 'chapter_id');
    }

    /**
     * Tier `exam_based`: average diagnostic-attempt percentage, chapter
     * grain. Not scoped by current enrollment - see class docblock.
     *
     * @param  array<int,int>  $chapterIds
     * @return Collection<int, object{avg_pct:float, n_attempts:int, n_students:int}> keyed by chapter_id
     */
    private function examBasedAchievement(array $chapterIds, array $filters): Collection
    {
        if (empty($chapterIds)) {
            return collect();
        }

        return DB::table('pal_diagnostic_attempt')
            ->whereIn('chapter_id', $chapterIds)
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->where('syear', $filters['syear'])
            ->where('status', 'submitted')
            ->selectRaw('chapter_id, avg(percentage) as avg_pct, count(*) as n_attempts, count(distinct student_id) as n_students')
            ->groupBy('chapter_id')
            ->get()
            ->keyBy('chapter_id');
    }

    /**
     * outcome_id -> its mapped concept rows, via lms_concept_outcome.
     *
     * @param  array<int,int>  $outcomeIds
     * @return Collection<int, Collection<int,object{concept_id:int,match_source:string,match_score:?float}>> keyed by outcome_id
     */
    private function outcomeConceptMap(array $outcomeIds): Collection
    {
        if (empty($outcomeIds)) {
            return collect();
        }

        return DB::table('lms_concept_outcome')
            ->whereIn('outcome_id', $outcomeIds)
            ->get(['outcome_id', 'concept_id', 'match_source', 'match_score'])
            ->groupBy('outcome_id');
    }

    /**
     * Pooled pal_concept_mastery evidence per concept: how many rows exist,
     * how many clear that row's own mastery_gate (-> Achievement), and the
     * sum of the raw continuous p_mastery probability (-> Mastery, a
     * genuinely different number from Achievement: a concept can be
     * individually below its gate yet still contribute a meaningful
     * fractional mastery score to the mean).
     *
     * @param  array<int,int>  $conceptIds
     * @return Collection<int, object{n_rows:int, n_pass:int, sum_mastery:float}> keyed by concept_id
     */
    private function masteryByConcept(array $conceptIds, int $subInstituteId): Collection
    {
        if (empty($conceptIds)) {
            return collect();
        }

        return DB::table('pal_concept_mastery')
            ->whereIn('concept_ref_id', $conceptIds)
            ->where('sub_institute_id', $subInstituteId)
            ->selectRaw('concept_ref_id, count(*) as n_rows, sum(p_mastery >= mastery_gate) as n_pass, sum(p_mastery) as sum_mastery')
            ->groupBy('concept_ref_id')
            ->get()
            ->keyBy('concept_ref_id');
    }

    /**
     * Tier `pal_verified`: for each outcome, pool mastery evidence across
     * every concept lms_concept_outcome maps it to. A pooled ratio/mean, not
     * an average of per-concept percentages, so a concept evaluated on 2
     * students does not get equal weight to one evaluated on 30.
     *
     * Returns two distinct numbers, matching the Achievement/Mastery split:
     *   value   - Achievement: the pass-rate against each row's own mastery_gate.
     *   mastery - Mastery: the mean of the raw continuous p_mastery probability.
     *
     * @return Collection<int, array{value:float, mastery:float, n_concepts:int, n_rows:int}> keyed by outcome_id
     */
    private function palVerifiedAchievement(Collection $outcomeConceptMap, array $subInstituteIds): Collection
    {
        if ($outcomeConceptMap->isEmpty()) {
            return collect();
        }

        $allConceptIds = $outcomeConceptMap->flatten(1)->pluck('concept_id')->unique()->values()->all();
        $masteryByConcept = $this->masteryByConcept($allConceptIds, $subInstituteIds[0]);

        $out = [];
        foreach ($outcomeConceptMap as $outcomeId => $concepts) {
            $conceptIds = $concepts->pluck('concept_id')->unique();
            $nRows = 0;
            $nPass = 0;
            $sumMastery = 0.0;

            foreach ($conceptIds as $conceptId) {
                $row = $masteryByConcept->get($conceptId);
                if ($row === null) {
                    continue;
                }
                $nRows += (int) $row->n_rows;
                $nPass += (int) $row->n_pass;
                $sumMastery += (float) $row->sum_mastery;
            }

            if ($nRows > 0) {
                $out[$outcomeId] = [
                    'value' => ($nPass / $nRows) * 100,
                    'mastery' => ($sumMastery / $nRows) * 100,
                    'n_concepts' => $conceptIds->count(),
                    'n_rows' => $nRows,
                ];
            }
        }

        return collect($out);
    }

    // -------------------------------------------------------------------
    // Blending, classification, labelling
    // -------------------------------------------------------------------

    /**
     * Achievement below this is "Good"; below this but above the next is
     * "Average"; below that is "Needs attention". Not this estate's own
     * invention - these are the exact 75/60 cut points used by the
     * reference LO Mastery report this feature was asked to match.
     */
    private const STATUS_GOOD_THRESHOLD = 75.0;
    private const STATUS_AVERAGE_THRESHOLD = 60.0;

    private function blendAchievement($exam, ?array $pal): array
    {
        // Diagnostic attempts give exactly one number - an average score -
        // so Achievement and Mastery coincide for this tier. There is no
        // second, independent signal to tell them apart at chapter grain.
        $examBlock = $exam ? [
            'value' => round((float) $exam->avg_pct, 1),
            'mastery' => round((float) $exam->avg_pct, 1),
            'tier' => 'exam_based',
            'source' => sprintf(
                'pal_diagnostic_attempt - %d attempt(s) across %d student(s), chapter grain',
                $exam->n_attempts,
                $exam->n_students
            ),
        ] : null;

        // pal_concept_mastery gives two independent numbers: a pass-rate
        // against each concept's own gate (Achievement) and the raw
        // continuous BKT probability (Mastery) - a concept just under its
        // gate still has a real, non-zero mastery estimate worth showing.
        $palBlock = $pal ? [
            'value' => round($pal['value'], 1),
            'mastery' => round($pal['mastery'], 1),
            'tier' => 'pal_verified',
            'source' => sprintf(
                'lms_concept_outcome -> pal_concept_mastery, %d mapped concept(s), %d evidence row(s), concept grain',
                $pal['n_concepts'],
                $pal['n_rows']
            ),
        ] : null;

        $chosen = $palBlock ?? $examBlock;

        if ($chosen === null) {
            return ['value' => null, 'mastery' => null, 'tier' => 'unavailable', 'source' => null, 'status' => null, 'secondary' => null];
        }

        return $chosen + [
            'status' => $this->statusFor($chosen['value']),
            'secondary' => $palBlock !== null ? $examBlock : null,
        ];
    }

    /** Good / Average / Needs attention, from Achievement - never from Mastery, matching the reference report. */
    private function statusFor(?float $achievement): ?string
    {
        if ($achievement === null) {
            return null;
        }

        if ($achievement >= self::STATUS_GOOD_THRESHOLD) {
            return 'good';
        }

        if ($achievement >= self::STATUS_AVERAGE_THRESHOLD) {
            return 'average';
        }

        return 'needs_attention';
    }

    private function deliveredLabel(string $status): string
    {
        return match ($status) {
            'Done' => 'delivered',
            'In progress' => 'in_progress',
            default => 'not_started',
        };
    }

    /**
     * Single-pass, priority-ordered so the three gap buckets never overlap:
     * not yet delivered beats unassessed, and unassessed beats low
     * achievement - an outcome can only ever need ONE next action.
     */
    private function classifyGap(string $delivered, string $assessed, array $achievement): string
    {
        if ($delivered !== 'delivered') {
            return 'delivery_gap';
        }

        if ($assessed !== 'assessed') {
            return 'assessment_gap';
        }

        if ($achievement['value'] !== null && $achievement['value'] < self::LEARNING_GAP_THRESHOLD) {
            return 'learning_gap';
        }

        return 'none';
    }

    private function gapExplanation(string $gapCategory, string $delivered, string $assessed, array $achievement): string
    {
        return match ($gapCategory) {
            'delivery_gap' => "Not yet fully delivered ({$delivered}) - teaching this chapter is the next step before assessment or achievement can be read.",
            'assessment_gap' => 'Delivered, but this chapter has no question-bank or diagnostic-attempt evidence yet - add questions or run a diagnostic to measure achievement.',
            'learning_gap' => sprintf(
                'Delivered and assessed, but achievement is %.1f%% (%s) - below the %.0f%% threshold. Consider reinforcement.',
                $achievement['value'],
                $achievement['tier'] === 'pal_verified' ? 'PAL-verified' : 'exam-based',
                self::LEARNING_GAP_THRESHOLD
            ),
            'not_applicable' => 'This is a curriculum-level goal or competency with no single chapter, so delivery, assessment and achievement are not tracked against it directly.',
            default => $achievement['value'] !== null
                ? sprintf('Delivered, assessed, and achievement is %.1f%% - above threshold on the %s signal.', $achievement['value'], $achievement['tier'] === 'pal_verified' ? 'PAL-verified' : 'exam-based')
                : 'Delivered and assessed, but no achievement evidence has been recorded yet.',
        };
    }

    private function healthStatus(int $expected, int $delivered, int $gapCount): string
    {
        if ($expected === 0) {
            return 'needs_attention';
        }

        $deliveredPct = ($delivered / $expected) * 100;
        $gapRatio = ($gapCount / $expected) * 100;

        if ($deliveredPct >= 80 && $gapRatio <= 10) {
            return 'on_track';
        }

        if ($deliveredPct >= 50 || $gapRatio <= 30) {
            return 'needs_attention';
        }

        return 'at_risk';
    }

    private function pct(int $numerator, int $denominator): int
    {
        return $denominator > 0 ? (int) round(($numerator / $denominator) * 100) : 0;
    }

    private function courseMasterLink($chapter, int $chapterId, string $view): ?string
    {
        if (!$chapter || !$chapter->subject_id || !$chapter->standard_id) {
            return null;
        }

        return sprintf(
            '/course-master/%d-%d/chapters?view=%s&chapterId=%d&expandedChapterId=%d',
            $chapter->subject_id,
            $chapter->standard_id,
            $view,
            $chapterId,
            $chapterId
        );
    }
}
