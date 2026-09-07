<?php

namespace App\Services\LessonIntelligence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lesson Intelligence - Phases 0, 1 and 2.
 *
 *   Phase 0  Data assembly + capacity computation. Reads the school's own
 *            scheduling tables (academic_year, timetable, period,
 *            calendar_events, result_create_exam) and the curriculum content
 *            tables. Zero LLM cost.
 *   Phase 1  Macro plan - distributes chapters across the term's teaching
 *            weeks proportionally to concept mastery minutes. Zero LLM cost.
 *   Phase 2  Meso plan - walks each teacher's calendar and splits concept
 *            mastery minutes into concrete dated period slots, writing
 *            lms_lesson_plan_periods + lms_lesson_plan_concepts. Zero LLM cost.
 *
 * Phase 3 (the per-period lesson content) lives in MicroPlannerService because
 * it is the only phase that calls an LLM.
 *
 * Ported from the pdf-extraction FastAPI service so the ERP owns the whole
 * pipeline; the SQL and the arithmetic are kept faithful to that original.
 */
class LessonIntelligenceService
{
    public const TBL_LESSON_PLANS  = 'lms_intelligence_lesson_plans';
    public const TBL_PLAN_PERIODS  = 'lms_lesson_plan_periods';
    public const TBL_PLAN_CONCEPTS = 'lms_lesson_plan_concepts';

    /** timetable.week_day codes -> Python-style weekday index (0 = Monday). */
    public const WEEKDAY_TO_IDX = ['M' => 0, 'T' => 1, 'W' => 2, 'H' => 3, 'F' => 4, 'S' => 5];

    public const WEEKDAY_LABELS = [
        'M' => 'Monday', 'T' => 'Tuesday', 'W' => 'Wednesday',
        'H' => 'Thursday', 'F' => 'Friday', 'S' => 'Saturday',
    ];

    public const DEFAULT_PERIOD_DURATION_MIN = 40;

    /**
     * Preferred institute to read shared curriculum content from. This is only
     * a tie-break: resolveContentSource searches every institute that actually
     * holds chapters and falls back to whichever one does, so a wrong value
     * here costs nothing. Scheduling always comes from the school's own
     * sub_institute_id.
     */
    public const MASTER_CONTENT_INSTITUTE_ID = 1;

    /* =====================================================================
     * Content-source resolution
     * ===================================================================== */

    /** Strip board prefixes so "CBSE-Class 10" and "Class 10" match. */
    protected function normaliseStandardName(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        return trim(str_replace(['cbse-', 'gseb-'], '', mb_strtolower($name)));
    }

    /** Collapse the subject spellings each school uses onto one canonical name. */
    protected function normaliseSubjectName(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        $n = trim(str_replace('(orals)', '', mb_strtolower(trim($name))));

        $aliases = [
            'maths'             => 'mathematics',
            'math'              => 'mathematics',
            'mathematics basic' => 'mathematics',
            'e.v.s'             => 'environmental studies',
            'evs'               => 'environmental studies',
            'social science'    => 'social sciences',
            'sst'               => 'social sciences',
            'sci'               => 'science',
            'sci.'              => 'science',
            'hindi'             => 'hindi-a',
            'eng'               => 'english',
            'g.k'               => 'general',
            'g.k.'              => 'general',
            'general knowledge' => 'general',
            'computer'          => 'it & computer',
            'computer science'  => 'it & computer',
            'physics'           => 'physical science',
            'p.e'               => 'health and physical education',
            'p.e.'              => 'health and physical education',
            'pt'                => 'health and physical education',
            'mass p.t.'         => 'health and physical education',
        ];

        return $aliases[$n] ?? $n;
    }

    /**
     * Translate a school's own standard/subject ids into the master institute's
     * equivalents by matching normalised names. Older installs have broken
     * cross-institute id links, so names are the only reliable join.
     *
     * @return array{0:int,1:int} [masterStandardId, masterSubjectId]
     */
    public function mapToMasterContentIds(int $schoolStandardId, int $schoolSubjectId): array
    {
        $stdName = DB::table('standard')->where('id', $schoolStandardId)->value('name');
        $subName = DB::table('subject')->where('id', $schoolSubjectId)->value('subject_name');

        if ($stdName === null || $subName === null) {
            return [$schoolStandardId, $schoolSubjectId];
        }

        $wantStd = $this->normaliseStandardName($stdName);
        $wantSub = $this->normaliseSubjectName($subName);

        $mappedStd = $schoolStandardId;
        $masterStandards = DB::table('standard')
            ->where('sub_institute_id', self::MASTER_CONTENT_INSTITUTE_ID)
            ->get(['id', 'name']);
        foreach ($masterStandards as $row) {
            if ($this->normaliseStandardName($row->name) === $wantStd) {
                $mappedStd = (int) $row->id;
                break;
            }
        }

        $mappedSub = $schoolSubjectId;
        $masterSubjects = DB::table('subject')
            ->where('sub_institute_id', self::MASTER_CONTENT_INSTITUTE_ID)
            ->get(['id', 'subject_name']);
        foreach ($masterSubjects as $row) {
            if ($this->normaliseSubjectName($row->subject_name) === $wantSub) {
                $mappedSub = (int) $row->id;
                break;
            }
        }

        return [$mappedStd, $mappedSub];
    }

    /**
     * Decide which (institute, standard, subject) triple actually holds the
     * chapters for a school's standard + subject.
     *
     * Curriculum content is authored once and shared, but not always under the
     * same institute, and never under the school's own standard/subject ids -
     * every institute has its own id space, so names are the only reliable
     * join. Rather than trusting one hardcoded "master" institute (which goes
     * stale the moment content is re-authored elsewhere), this searches every
     * institute that genuinely has chapters and picks the best name match,
     * preferring MASTER_CONTENT_INSTITUTE_ID only as a tie-break.
     *
     * Returns the name-mapped master coordinates when nothing matches, so an
     * unprocessed subject still produces a meaningful error message.
     *
     * @return array{0:int,1:int,2:int} [instituteId, standardId, subjectId]
     */
    public function resolveContentSource(int $schoolStandardId, int $schoolSubjectId): array
    {
        $stdName = DB::table('standard')->where('id', $schoolStandardId)->value('name');
        $subName = DB::table('subject')->where('id', $schoolSubjectId)->value('subject_name');

        $wantStd = $this->normaliseStandardName($stdName);
        $wantSub = $this->normaliseSubjectName($subName);

        // Only a few hundred distinct triples hold chapters, so this is cheap.
        $candidates = DB::table('chapter_master as cm')
            ->join('standard as s', 'cm.standard_id', '=', 's.id')
            ->join('subject as sj', 'cm.subject_id', '=', 'sj.id')
            ->where('cm.availability', 1)
            ->groupBy('cm.sub_institute_id', 'cm.standard_id', 'cm.subject_id', 's.name', 'sj.subject_name')
            ->get([
                'cm.sub_institute_id', 'cm.standard_id', 'cm.subject_id',
                's.name as std_name', 'sj.subject_name as sub_name',
                DB::raw('COUNT(*) as chapters'),
            ]);

        $matches = [];
        foreach ($candidates as $c) {
            if ($wantStd !== ''
                && $wantSub !== ''
                && $this->normaliseStandardName($c->std_name) === $wantStd
                && $this->normaliseSubjectName($c->sub_name) === $wantSub) {
                $matches[] = $c;
            }
        }

        if ($matches) {
            usort($matches, function ($a, $b) {
                $aPreferred = (int) $a->sub_institute_id === self::MASTER_CONTENT_INSTITUTE_ID;
                $bPreferred = (int) $b->sub_institute_id === self::MASTER_CONTENT_INSTITUTE_ID;
                if ($aPreferred !== $bPreferred) {
                    return $aPreferred ? -1 : 1;
                }

                return (int) $b->chapters <=> (int) $a->chapters;
            });

            $best = $matches[0];

            return [(int) $best->sub_institute_id, (int) $best->standard_id, (int) $best->subject_id];
        }

        // No name match anywhere - try the school's own ids verbatim, in case
        // the subject was authored in place.
        foreach ($candidates as $c) {
            if ((int) $c->standard_id === $schoolStandardId && (int) $c->subject_id === $schoolSubjectId) {
                Log::info("LessonIntelligence: no name match for std={$schoolStandardId} sub={$schoolSubjectId}; "
                    . "using institute {$c->sub_institute_id} with the school's own ids");

                return [(int) $c->sub_institute_id, $schoolStandardId, $schoolSubjectId];
            }
        }

        [$mappedStd, $mappedSub] = $this->mapToMasterContentIds($schoolStandardId, $schoolSubjectId);

        Log::warning("LessonIntelligence: no chapters anywhere for std={$schoolStandardId} ('{$stdName}') "
            . "sub={$schoolSubjectId} ('{$subName}')");

        return [self::MASTER_CONTENT_INSTITUTE_ID, $mappedStd, $mappedSub];
    }

    /* =====================================================================
     * PHASE 0A - Data assembly
     * ===================================================================== */

    /** Term/semester dates for a school year. */
    public function getTerms(int $subInstituteId, int $syear): array
    {
        $terms = DB::table('academic_year')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->orderBy('sort_order')
            ->get(['term_id', 'title', 'start_date', 'end_date', 'post_start_date', 'post_end_date'])
            ->map(fn ($r) => [
                'term_id'         => (int) $r->term_id,
                'title'           => $r->title,
                'start_date'      => $this->toDate($r->start_date),
                'end_date'        => $this->toDate($r->end_date),
                'post_start_date' => $this->toDate($r->post_start_date),
                'post_end_date'   => $this->toDate($r->post_end_date),
            ])
            ->all();

        return $this->normaliseTermWindows($terms);
    }

    /**
     * The year whose timetable should be used to schedule a plan.
     *
     * A plan is recorded under the year the calendar displays, which is not
     * always the year its timetable lives in - institute 1 records plans under
     * 2026 while its only timetable is 2022. Phase 2 rebuilds the calendar from
     * the stored plan, so it has to ask this rather than trust the plan's own
     * syear, or it finds no slots and refuses to schedule anything.
     *
     * Prefers the plan's own year whenever that year really has a timetable, so
     * correctly recorded plans resolve to themselves and nothing changes.
     */
    public function resolveSchedulingYear(
        int $subInstituteId,
        int $standardId,
        int $subjectId,
        int $planYear,
        ?int $divisionId = null
    ): int {
        $query = fn () => DB::table('timetable as t')
            ->join('period as p', 't.period_id', '=', 'p.id')
            ->where('t.sub_institute_id', $subInstituteId)
            ->where('t.standard_id', $standardId)
            ->where('t.subject_id', $subjectId)
            ->when($divisionId !== null, fn ($q) => $q->where('t.division_id', $divisionId));

        if ($query()->where('t.syear', $planYear)->exists()) {
            return $planYear;
        }

        $fallback = $query()->max('t.syear');

        if ($fallback !== null && (int) $fallback !== $planYear) {
            Log::info("LessonIntelligence: plan year {$planYear} has no timetable for "
                . "inst={$subInstituteId} std={$standardId} sub={$subjectId}; scheduling from {$fallback}");
        }

        return $fallback !== null ? (int) $fallback : $planYear;
    }

    /**
     * Bring term windows back inside what an academic year can actually be.
     *
     * The planner trusts these dates completely - it walks them day by day - so a
     * mistyped end date does not fail loudly, it silently produces a plan of the
     * wrong shape. A "SEMESTER-1" recorded as 2022-04-01..2026-12-31 spreads one
     * year of syllabus over five years, roughly one lesson a week forever, which
     * looks to a teacher exactly like the generator not working.
     *
     * Two invariants fix that without anyone editing rows:
     *
     *   1. Terms run in sequence. A term that overruns the next term's start is
     *      cut back to the day before it.
     *   2. An academic year lasts a year. Nothing may end more than twelve months
     *      after the first term begins.
     *
     * Correctly entered years are untouched - both rules are already true of them.
     * Each adjusted term carries `window_adjusted` plus its original end so the
     * capacity report can say the dates were corrected rather than hiding it.
     */
    protected function normaliseTermWindows(array $terms): array
    {
        $dated = array_values(array_filter(
            $terms,
            fn ($t) => $t['start_date'] instanceof CarbonImmutable && $t['end_date'] instanceof CarbonImmutable
        ));

        if (!$dated) {
            return $terms;
        }

        usort($dated, fn ($a, $b) => $a['start_date'] <=> $b['start_date']);

        // A year from the first term's start is the outer bound for everything.
        $yearEnd = $dated[0]['start_date']->addYear()->subDay();

        foreach ($dated as $i => $term) {
            $limit = $yearEnd;

            $next = $dated[$i + 1] ?? null;
            if ($next) {
                $beforeNext = $next['start_date']->subDay();
                if ($beforeNext->lessThan($limit)) {
                    $limit = $beforeNext;
                }
            }

            if ($term['end_date']->greaterThan($limit)) {
                // Never clamp past the start - that would invert the term, and a
                // term shorter than its own start is not something to guess at.
                if ($limit->greaterThan($term['start_date'])) {
                    Log::info('LessonIntelligence: term ' . ($term['title'] ?? $term['term_id'])
                        . ' ends ' . $term['end_date']->toDateString()
                        . ', beyond its academic year; planning to ' . $limit->toDateString() . ' instead');

                    $dated[$i]['original_end_date'] = $term['end_date'];
                    $dated[$i]['window_adjusted']   = true;
                    $dated[$i]['end_date']          = $limit;
                }
            }
        }

        return $dated;
    }

    /**
     * Weekly timetable for one standard + subject (optionally one division).
     *
     * @return array{schedule:array,periods_per_week:int,teacher_id:?int,has_saturday:bool}
     */
    public function getWeeklySchedule(
        int $subInstituteId,
        int $standardId,
        int $subjectId,
        int $syear,
        ?int $divisionId = null
    ): array {
        $query = DB::table('timetable as t')
            ->join('period as p', 't.period_id', '=', 'p.id')
            ->where('t.sub_institute_id', $subInstituteId)
            ->where('t.standard_id', $standardId)
            ->where('t.subject_id', $subjectId)
            ->where('t.syear', $syear);

        if ($divisionId !== null) {
            $query->where('t.division_id', $divisionId);
        }

        $rows = $query
            ->orderByRaw("FIELD(t.week_day, 'M','T','W','H','F','S')")
            ->orderBy('p.sort_order')
            ->get([
                't.week_day', 't.period_id', 't.teacher_id', 't.division_id',
                'p.short_name as period_slot', 'p.sort_order',
                'p.start_time', 'p.end_time', 'p.length',
            ]);

        if ($rows->isEmpty()) {
            return [
                'schedule'         => [],
                'periods_per_week' => 0,
                'teacher_id'       => null,
                'has_saturday'     => false,
            ];
        }

        $schedule = [];
        foreach ($rows as $r) {
            $schedule[$r->week_day][] = [
                'period_id'  => (int) $r->period_id,
                'slot'       => $r->period_slot,
                'sort_order' => (int) $r->sort_order,
                'start_time' => $r->start_time !== null ? (string) $r->start_time : null,
                'end_time'   => $r->end_time !== null ? (string) $r->end_time : null,
                'length'     => (int) ($r->length ?: 0),
                'teacher_id' => $r->teacher_id !== null ? (int) $r->teacher_id : null,
            ];
        }

        $first = $rows->first();

        return [
            'schedule'         => $schedule,
            'periods_per_week' => $rows->count(),
            'teacher_id'       => $first->teacher_id !== null ? (int) $first->teacher_id : null,
            'has_saturday'     => array_key_exists('S', $schedule),
        ];
    }

    /**
     * School-wide average period length in minutes.
     * Falls back period.length -> end_time minus start_time -> 40.
     */
    public function getPeriodDuration(int $subInstituteId, int $syear): int
    {
        $default = self::DEFAULT_PERIOD_DURATION_MIN;

        $row = DB::table('timetable as t')
            ->join('period as p', 't.period_id', '=', 'p.id')
            ->where('t.sub_institute_id', $subInstituteId)
            ->where('t.syear', $syear)
            ->selectRaw("AVG(
                CASE
                    WHEN p.length > 0 THEN CAST(p.length AS UNSIGNED)
                    WHEN p.start_time != '00:00:00' AND p.end_time != '00:00:00'
                         THEN TIMESTAMPDIFF(MINUTE, p.start_time, p.end_time)
                    ELSE ?
                END
            ) AS avg_duration", [$default])
            ->first();

        if ($row && $row->avg_duration !== null && (float) $row->avg_duration > 0) {
            return max((int) round((float) $row->avg_duration), 20); // 20-minute sanity floor
        }

        return $default;
    }

    /**
     * Holidays and vacations. Generic auto-generated "HOLIDAY" rows that land on
     * a Saturday are ignored, otherwise every Saturday timetable slot would be
     * wiped out on schools that do teach on Saturdays.
     */
    public function getHolidays(int $subInstituteId, int $syear): array
    {
        $rows = DB::table('calendar_events')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->whereIn('event_type', ['holiday', 'vacation'])
            ->orderBy('school_date')
            ->get(['school_date', 'title', 'event_type']);

        $holidays = [];
        foreach ($rows as $r) {
            $date  = $this->toDate($r->school_date);
            $title = (string) ($r->title ?? '');

            if ($date !== null
                && $date->dayOfWeek === CarbonImmutable::SATURDAY
                && strtoupper(trim($title)) === 'HOLIDAY') {
                continue;
            }

            $holidays[] = ['date' => $date, 'title' => $title, 'type' => $r->event_type];
        }

        return $holidays;
    }

    /** Exam dates for one standard + subject. */
    public function getExamDates(int $subInstituteId, int $standardId, int $subjectId, int $syear): array
    {
        return DB::table('result_create_exam')
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->where('syear', $syear)
            ->whereNotNull('exam_date')
            ->orderBy('exam_date')
            ->get(['exam_date', 'title', 'points', 'term_id'])
            ->map(fn ($r) => [
                'date'    => $this->toDate($r->exam_date),
                'title'   => $r->title,
                'marks'   => $r->points,
                'term_id' => $r->term_id,
            ])
            ->all();
    }

    /**
     * Concept mastery minutes for a subject, in teaching order.
     *
     * Reached through chapter_master rather than lms_concept.extraction_id: the
     * extraction link is not always populated, and going via the chapter is
     * what guarantees the minutes counted here are the same ones Phase 2 later
     * schedules.
     */
    public function getConceptsForSubject(int $instituteId, int $standardId, int $subjectId): array
    {
        return DB::table('lms_concept as c')
            ->join('chapter_master as cm', 'c.chapter_id', '=', 'cm.id')
            ->where('cm.sub_institute_id', $instituteId)
            ->where('cm.standard_id', $standardId)
            ->where('cm.subject_id', $subjectId)
            ->where('cm.availability', 1)
            ->orderBy('cm.sort_order')
            ->orderBy('c.id')
            ->get([
                'c.id as concept_id', 'c.name as concept_name', 'c.description',
                'c.estimated_mastery_minutes', 'c.mastery_threshold',
                'cm.id as chapter_id', 'cm.chapter_name', 'cm.sort_order',
            ])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /** Curriculum metadata for a standard + subject, from the resolved institute. */
    public function getCurriculum(int $instituteId, int $standardId, int $subjectId): ?array
    {
        $row = DB::table('lms_curriculum')
            ->where('sub_institute_id', $instituteId)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->orderByDesc('id')
            ->first([
                'id', 'curriculum_name', 'objective', 'board', 'framework',
                'total_marks', 'internal_marks', 'curriculum_alignment',
                'holistic_curriculum', 'model_integration',
            ]);

        return $row ? (array) $row : null;
    }

    /** Units group chapters into thematic blocks. */
    public function getUnits(int $curriculumId): array
    {
        return DB::table('lms_units')
            ->where('curriculum_id', $curriculumId)
            ->orderBy('unit_number')
            ->get(['id', 'unit_number', 'name', 'planned_periods', 'unit_chapters'])
            ->map(function ($r) {
                $unit = (array) $r;
                $unit['unit_chapters'] = $this->decodeJson($unit['unit_chapters'] ?? null);

                return $unit;
            })
            ->all();
    }

    /** Official NCF/NCERT competency goals for a standard + subject. */
    public function getLearningOutcomes(int $standardId, int $subjectId): array
    {
        return DB::table('lms_learning_outcomes')
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->orderBy('id')
            ->get(['id', 'code', 'type', 'description', 'chapter_id', 'parent_id'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /** Per-chapter AI-generated intelligence (objectives, pedagogy, misconceptions...). */
    public function getSemanticIntelligence(int $standardId, int $subjectId): array
    {
        $jsonCols = [
            'learning_objectives', 'learning_outcomes', 'ability', 'knowledge',
            'skill', 'competency', 'blooms_level', 'dok', 'pedagogy',
            'misconceptions', 'real_world_applications', 'prerequisites',
            'assessment_blueprint',
        ];

        return DB::table('semantic_intelligence')
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->orderBy('chapter_number')
            ->get([
                'id', 'chapter_id', 'chapter_number',
                'learning_objective', 'learning_objectives', 'learning_outcomes',
                'ability', 'knowledge', 'skill', 'competency',
                'blooms_level', 'dok', 'pedagogy',
                'misconceptions', 'real_world_applications',
                'prerequisites', 'assessment_blueprint',
                'total_concepts as total_topics',
            ])
            ->map(function ($r) use ($jsonCols) {
                $row = (array) $r;
                foreach ($jsonCols as $col) {
                    if (!empty($row[$col]) && is_string($row[$col])) {
                        $row[$col] = $this->decodeJson($row[$col]);
                    }
                }

                return $row;
            })
            ->all();
    }

    /**
     * Read every scheduling and content input the later phases need.
     *
     * Scheduling comes from the school's own sub_institute_id; curriculum
     * content from whichever institute resolveContentSource picked.
     */
    public function assembleSchoolData(
        int $subInstituteId,
        int $standardId,
        int $subjectId,
        int $syear,
        ?int $divisionId = null
    ): array {
        $terms = $this->getTerms($subInstituteId, $syear);
        if (!$terms) {
            Log::warning("LessonIntelligence: no terms for inst={$subInstituteId} year={$syear}");
        }

        $weekly         = $this->getWeeklySchedule($subInstituteId, $standardId, $subjectId, $syear, $divisionId);
        $periodDuration = $this->getPeriodDuration($subInstituteId, $syear);
        $holidays       = $this->getHolidays($subInstituteId, $syear);
        $exams          = $this->getExamDates($subInstituteId, $standardId, $subjectId, $syear);

        [$contentInstId, $mappedStd, $mappedSub] = $this->resolveContentSource($standardId, $subjectId);

        $curriculum = $this->getCurriculum($contentInstId, $mappedStd, $mappedSub);
        $units      = $curriculum ? $this->getUnits((int) $curriculum['id']) : [];
        $concepts   = $this->getConceptsForSubject($contentInstId, $mappedStd, $mappedSub);

        return [
            'sub_institute_id'      => $subInstituteId,
            'standard_id'           => $standardId,
            'subject_id'            => $subjectId,
            'syear'                 => $syear,
            'division_id'           => $divisionId,
            'content_institute_id'  => $contentInstId,
            'mapped_standard_id'    => $mappedStd,
            'mapped_subject_id'     => $mappedSub,
            'terms'                 => $terms,
            'weekly_schedule'       => $weekly['schedule'],
            'periods_per_week'      => $weekly['periods_per_week'],
            'teacher_id'            => $weekly['teacher_id'],
            'has_saturday'          => $weekly['has_saturday'],
            'period_duration_min'   => $periodDuration,
            'holidays'              => $holidays,
            'exam_dates'            => $exams,
            'concepts'              => $concepts,
            'total_concept_minutes' => array_sum(array_map(
                fn ($c) => (int) ($c['estimated_mastery_minutes'] ?? 0),
                $concepts
            )),
            'curriculum'            => $curriculum,
            'units'                 => $units,
            'learning_outcomes'     => $this->getLearningOutcomes($mappedStd, $mappedSub),
            'semantic_intelligence' => $this->getSemanticIntelligence($mappedStd, $mappedSub),
        ];
    }

    /* =====================================================================
     * PHASE 0B - Capacity computation (pure arithmetic)
     * ===================================================================== */

    /**
     * Walk the term day by day counting available teaching days per weekday
     * code. Sundays, holidays and exam days never count; Saturdays only count
     * for schools that actually timetable them.
     *
     * @return array<string,int>
     */
    protected function countTeachingDaysPerWeekday(
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $holidaySet,
        array $examSet,
        bool $hasSaturday
    ): array {
        $counts  = array_fill_keys(array_keys(self::WEEKDAY_TO_IDX), 0);
        $current = $start;

        while ($current->lessThanOrEqualTo($end)) {
            $code = $this->weekdayCode($current);

            // Sunday has no code, so it is skipped along with anything unmapped.
            if ($code !== null && ($code !== 'S' || $hasSaturday)) {
                $key = $current->toDateString();
                if (!isset($holidaySet[$key]) && !isset($examSet[$key])) {
                    $counts[$code]++;
                }
            }

            $current = $current->addDay();
        }

        return $counts;
    }

    /** Bucket a buffer percentage into the four capacity labels. */
    protected function bufferStatus(float $bufferPercent): string
    {
        if ($bufferPercent > 15) {
            return 'COMFORTABLE';
        }
        if ($bufferPercent > 5) {
            return 'GOOD';
        }

        return $bufferPercent > 0 ? 'TIGHT' : 'OVERLOADED';
    }

    /**
     * Per-term capacity: teaching days, available periods and minutes, the
     * minutes the syllabus actually needs, and the resulting buffer.
     */
    public function computeCapacity(array $schoolData): array
    {
        $terms = $schoolData['terms'] ?? [];
        if (!$terms) {
            return [];
        }

        $schedule       = $schoolData['weekly_schedule'] ?? [];
        $periodDuration = (int) ($schoolData['period_duration_min'] ?? self::DEFAULT_PERIOD_DURATION_MIN);
        $hasSaturday    = (bool) ($schoolData['has_saturday'] ?? false);

        $holidaySet = $this->dateSet($schoolData['holidays'] ?? []);
        $examSet    = $this->dateSet($schoolData['exam_dates'] ?? []);

        $totalRequired = (int) ($schoolData['total_concept_minutes'] ?? 0);

        // Total term span, used to split the syllabus across multiple terms.
        $totalTermDays = 0;
        foreach ($terms as $t) {
            if ($t['start_date'] instanceof CarbonImmutable && $t['end_date'] instanceof CarbonImmutable) {
                $totalTermDays += $t['start_date']->diffInDays($t['end_date']);
            }
        }

        $results = [];
        foreach ($terms as $term) {
            $start = $term['start_date'];
            $end   = $term['end_date'];

            if (!$start instanceof CarbonImmutable || !$end instanceof CarbonImmutable) {
                Log::warning('LessonIntelligence: skipping term ' . ($term['title'] ?? '?') . ' - invalid dates');
                continue;
            }
            if ($end->lessThanOrEqualTo($start)) {
                Log::warning('LessonIntelligence: skipping term ' . ($term['title'] ?? '?') . ' - end <= start');
                continue;
            }

            $holidaysInTerm = 0;
            foreach (array_keys($holidaySet) as $key) {
                if ($key >= $start->toDateString() && $key <= $end->toDateString()) {
                    $holidaysInTerm++;
                }
            }

            $examsInTerm = 0;
            foreach (array_keys($examSet) as $key) {
                if ($key >= $start->toDateString() && $key <= $end->toDateString()) {
                    $examsInTerm++;
                }
            }

            $dayCounts = $this->countTeachingDaysPerWeekday($start, $end, $holidaySet, $examSet, $hasSaturday);

            // Every timetabled slot on a weekday, once per available day.
            $totalPeriods = 0;
            foreach ($schedule as $dayCode => $slots) {
                $totalPeriods += ($dayCounts[$dayCode] ?? 0) * count($slots);
            }

            $totalMinutes  = $totalPeriods * $periodDuration;
            $totalRawDays  = $start->diffInDays($end) + 1;
            $daysPerWeek   = $hasSaturday ? 6 : 5;
            $teachingWeeks = round(array_sum($dayCounts) / $daysPerWeek, 1);

            if (count($terms) > 1) {
                $termFraction = $totalTermDays > 0
                    ? $start->diffInDays($end) / $totalTermDays
                    : 1 / count($terms);
                $termRequired = (int) ($totalRequired * $termFraction);
            } else {
                $termRequired = $totalRequired;
            }

            $bufferMinutes = $totalMinutes - $termRequired;
            $bufferPercent = $totalMinutes > 0
                ? round($bufferMinutes / $totalMinutes * 100, 1)
                : 0.0;

            $labelledDays = [];
            foreach ($dayCounts as $code => $count) {
                if ($count > 0) {
                    $labelledDays[self::WEEKDAY_LABELS[$code] ?? $code] = $count;
                }
            }

            $results[] = [
                'term_id'                  => $term['term_id'] ?? null,
                'term_title'               => $term['title'] ?? null,
                'term_start'               => $start->toDateString(),
                'term_end'                 => $end->toDateString(),
                // Set when academic_year gave a window that could not be a term;
                // the UI says so rather than quietly planning different dates.
                'window_adjusted'          => (bool) ($term['window_adjusted'] ?? false),
                'original_term_end'        => isset($term['original_end_date'])
                    ? $term['original_end_date']->toDateString()
                    : null,
                'total_raw_days'           => $totalRawDays,
                'teaching_weeks'           => $teachingWeeks,
                'teaching_days_by_weekday' => $labelledDays,
                'holidays_in_term'         => $holidaysInTerm,
                'exam_days_in_term'        => $examsInTerm,
                'total_teaching_periods'   => $totalPeriods,
                'period_duration_min'      => $periodDuration,
                'total_teaching_minutes'   => $totalMinutes,
                'total_teaching_hours'     => round($totalMinutes / 60, 1),
                'required_minutes'         => $termRequired,
                'buffer_minutes'           => $bufferMinutes,
                'buffer_percent'           => $bufferPercent,
                'status'                   => $this->bufferStatus((float) $bufferPercent),
            ];
        }

        return $results;
    }

    /** Full Phase 0: assemble the data then compute capacity, ready for the API. */
    public function getCapacityAnalysis(
        int $subInstituteId,
        int $standardId,
        int $subjectId,
        int $syear,
        ?int $divisionId = null
    ): array {
        Log::info("LessonIntelligence: capacity analysis inst={$subInstituteId} std={$standardId} sub={$subjectId} year={$syear}");

        // Plan from the year that actually has a timetable. Phase 2 already does
        // this when rebuilding a calendar; doing it here too keeps what the panel
        // reports and what the generator can build from the same year, instead of
        // the panel saying "no periods" about a class the generator can schedule.
        $schedulingYear = $this->resolveSchedulingYear(
            $subInstituteId,
            $standardId,
            $subjectId,
            $syear,
            $divisionId
        );

        $schoolData = $this->assembleSchoolData($subInstituteId, $standardId, $subjectId, $schedulingYear, $divisionId);
        $capacity   = $this->computeCapacity($schoolData);

        $grandPeriods  = array_sum(array_column($capacity, 'total_teaching_periods'));
        $grandMinutes  = array_sum(array_column($capacity, 'total_teaching_minutes'));
        $grandRequired = (int) $schoolData['total_concept_minutes'];
        $grandBuffer   = $grandMinutes - $grandRequired;
        $grandBufferPc = $grandMinutes > 0 ? round($grandBuffer / $grandMinutes * 100, 1) : 0.0;

        // Chapter-level breakdown of where the required minutes come from.
        $chapters = [];
        foreach ($schoolData['concepts'] as $c) {
            $name = $c['chapter_name'] ?? 'Unknown';
            if (!isset($chapters[$name])) {
                $chapters[$name] = [
                    'chapter_id'            => $c['chapter_id'] ?? null,
                    'chapter_name'          => $name,
                    'sort_order'            => $c['sort_order'] ?? null,
                    'concept_count'         => 0,
                    'total_mastery_minutes' => 0,
                    'concepts'              => [],
                ];
            }

            $chapters[$name]['concept_count']++;
            $chapters[$name]['total_mastery_minutes'] += (int) ($c['estimated_mastery_minutes'] ?? 0);
            $chapters[$name]['concepts'][] = [
                'concept_id'        => $c['concept_id'],
                'name'              => $c['concept_name'],
                'mastery_minutes'   => (int) ($c['estimated_mastery_minutes'] ?? 0),
                'mastery_threshold' => $c['mastery_threshold'] ?? null,
            ];
        }

        $chapterList = array_values($chapters);
        usort($chapterList, fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        $weeklyScheduleLabelled = [];
        foreach ($schoolData['weekly_schedule'] as $code => $slots) {
            $weeklyScheduleLabelled[self::WEEKDAY_LABELS[$code] ?? $code] = array_column($slots, 'slot');
        }

        return [
            'school_data' => [
                'sub_institute_id'    => $subInstituteId,
                'standard_id'         => $standardId,
                'subject_id'          => $subjectId,
                'syear'               => $syear,
                // Which year the timetable, terms and holidays were read from.
                // Differs from `syear` when the selected year has no timetable
                // but an earlier one does - the UI says so rather than implying
                // these numbers describe the selected year.
                'scheduling_syear'    => $schedulingYear,
                'division_id'         => $divisionId,
                'teacher_id'          => $schoolData['teacher_id'],
                'periods_per_week'    => $schoolData['periods_per_week'],
                'period_duration_min' => $schoolData['period_duration_min'],
                'has_saturday'        => $schoolData['has_saturday'],
                'weekly_schedule'     => $weeklyScheduleLabelled,
            ],
            'terms'        => $capacity,
            'grand_totals' => [
                'total_periods'          => $grandPeriods,
                'total_teaching_minutes' => $grandMinutes,
                'total_teaching_hours'   => round($grandMinutes / 60, 1),
                'total_required_minutes' => $grandRequired,
                'buffer_minutes'         => $grandBuffer,
                'buffer_percent'         => $grandBufferPc,
                'status'                 => $this->bufferStatus((float) $grandBufferPc),
            ],
            'content_breakdown' => [
                'total_chapters'        => count($chapterList),
                'total_concepts'        => count($schoolData['concepts']),
                'total_concept_minutes' => $grandRequired,
                'chapters'              => $chapterList,
            ],
            'calendar_data' => [
                'holidays' => array_map(fn ($h) => [
                    'date'  => $h['date'] instanceof CarbonImmutable ? $h['date']->toDateString() : $h['date'],
                    'title' => $h['title'],
                    'type'  => $h['type'],
                ], $schoolData['holidays']),
                'exams' => array_map(fn ($e) => [
                    'date'  => $e['date'] instanceof CarbonImmutable ? $e['date']->toDateString() : $e['date'],
                    'title' => $e['title'],
                    'marks' => $e['marks'],
                ], $schoolData['exam_dates']),
            ],
        ];
    }

    /* =====================================================================
     * Shared scheduling primitives (used by both macro and meso)
     * ===================================================================== */

    /**
     * Expand a term into one entry per bookable period slot, in chronological
     * order. This is the spine both later phases walk: Phase 1 counts the slots
     * to size chapter allocations, Phase 2 writes one row per slot.
     *
     * @return array<int,array>
     */
    public function buildTeachingCalendar(
        CarbonImmutable $termStart,
        CarbonImmutable $termEnd,
        array $schedule,
        array $holidaySet,
        array $examSet,
        bool $hasSaturday
    ): array {
        $slots      = [];
        $current    = $termStart;
        $weekNumber = 1;
        $prevMonday = $termStart->subDays($this->weekdayIndex($termStart));

        while ($current->lessThanOrEqualTo($termEnd)) {
            $thisMonday = $current->subDays($this->weekdayIndex($current));
            if ($thisMonday->greaterThan($prevMonday)) {
                $weekNumber++;
                $prevMonday = $thisMonday;
            }

            $code = $this->weekdayCode($current);
            $key  = $current->toDateString();

            $bookable = $code !== null
                && ($code !== 'S' || $hasSaturday)
                && !isset($holidaySet[$key])
                && !isset($examSet[$key]);

            if ($bookable) {
                foreach ($schedule[$code] ?? [] as $p) {
                    $slots[] = [
                        'date'        => $current,
                        'week_day'    => $code,
                        'week_number' => $weekNumber,
                        'period_id'   => $p['period_id'],
                        'period_slot' => $p['slot'],
                        'sort_order'  => $p['sort_order'] ?? 0,
                        'teacher_id'  => $p['teacher_id'] ?? null,
                    ];
                }
            }

            $current = $current->addDay();
        }

        return $slots;
    }

    /**
     * Chapters for a subject with their aggregated concept mastery minutes.
     * Chapters with no concepts still come back (with zeroes) so they are not
     * silently dropped from the plan.
     */
    public function getChaptersForSubject(int $instituteId, int $standardId, int $subjectId): array
    {
        return DB::table('chapter_master as cm')
            ->leftJoin('lms_concept as c', 'c.chapter_id', '=', 'cm.id')
            ->where('cm.sub_institute_id', $instituteId)
            ->where('cm.standard_id', $standardId)
            ->where('cm.subject_id', $subjectId)
            ->where('cm.availability', 1)
            ->groupBy('cm.id', 'cm.chapter_name', 'cm.sort_order', 'cm.extraction_id', 'cm.unit_id')
            ->orderBy('cm.sort_order')
            ->get([
                'cm.id as chapter_id', 'cm.chapter_name', 'cm.sort_order',
                'cm.extraction_id', 'cm.unit_id',
                DB::raw('COALESCE(SUM(c.estimated_mastery_minutes), 0) AS total_mastery_minutes'),
                DB::raw('COUNT(c.id) AS concept_count'),
            ])
            ->map(fn ($r) => [
                'chapter_id'            => (int) $r->chapter_id,
                'chapter_name'          => $r->chapter_name,
                'sort_order'            => $r->sort_order !== null ? (int) $r->sort_order : null,
                'extraction_id'         => $r->extraction_id,
                'unit_id'               => $r->unit_id,
                'total_mastery_minutes' => (int) $r->total_mastery_minutes,
                'concept_count'         => (int) $r->concept_count,
            ])
            ->all();
    }

    /* =====================================================================
     * Shared helpers
     * ===================================================================== */

    protected function toDate($value): ?CarbonImmutable
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Python's date.weekday(): 0 = Monday ... 6 = Sunday. */
    protected function weekdayIndex(CarbonImmutable $date): int
    {
        return ($date->dayOfWeek + 6) % 7;
    }

    protected function weekdayCode(CarbonImmutable $date): ?string
    {
        $code = array_search($this->weekdayIndex($date), self::WEEKDAY_TO_IDX, true);

        return $code === false ? null : $code;
    }

    /**
     * Build a lookup of Y-m-d keys from rows carrying a 'date'.
     *
     * @return array<string,true>
     */
    protected function dateSet(array $rows): array
    {
        $set = [];
        foreach ($rows as $row) {
            $date = $row['date'] ?? null;
            if ($date instanceof CarbonImmutable) {
                $set[$date->toDateString()] = true;
            }
        }

        return $set;
    }

    protected function decodeJson($value)
    {
        if ($value === null || $value === '' || !is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * decodeJson for the sibling phase services and the controller, which read
     * the same JSON columns (macro_plan_json, plan_json, learning_objectives)
     * and must tolerate them arriving as either a string or an array.
     */
    public function decodeJsonPublic($value)
    {
        return $this->decodeJson($value);
    }
}
