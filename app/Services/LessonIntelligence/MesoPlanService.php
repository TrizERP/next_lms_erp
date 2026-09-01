<?php

namespace App\Services\LessonIntelligence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Phase 2 - Meso plan.
 *
 * Takes the macro plan's chapter-to-week allocation and turns it into concrete,
 * dated rows: one lms_lesson_plan_periods row per bookable slot, each linked to
 * the concepts it covers via lms_lesson_plan_concepts.
 *
 * Concepts are consumed by their estimated_mastery_minutes, so a long concept
 * spans several periods and short ones share a period. Every sixth consecutive
 * teaching period becomes a revision slot.
 *
 * Pure arithmetic - no LLM call.
 */
class MesoPlanService
{
    /** After this many consecutive teaching periods, insert a revision slot. */
    private const REVISION_AFTER_CONSECUTIVE = 5;

    public function __construct(private LessonIntelligenceService $intel)
    {
    }

    /**
     * The teachers who own slots in this plan's calendar, plus the chapters from
     * the macro plan, so the UI can offer a manual chapter-to-teacher mapping.
     */
    public function getTeacherAssignmentOptions(int $planId): array
    {
        $plan = $this->loadPlan($planId);
        [$calendar] = $this->rebuildCalendar($plan);

        $teacherIds = array_values(array_unique(array_filter(array_column($calendar, 'teacher_id'))));

        $teachers = $teacherIds
            ? DB::table('tbluser')
                ->whereIn('id', $teacherIds)
                ->get(['id', DB::raw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) as name")])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => trim($r->name) ?: "Teacher {$r->id}"])
                ->all()
            : [];

        $macroPlan = $this->intel->decodeJsonPublic($plan->macro_plan_json);

        return [
            'status'   => 'success',
            'teachers' => $teachers,
            'chapters' => $macroPlan['chapter_schedule'] ?? [],
        ];
    }

    /**
     * Generate the dated period schedule for a plan.
     *
     * This is destructive by design: it deletes the plan's existing periods
     * (cascading to their concept links) and rebuilds them, so re-running after
     * a timetable change produces a clean schedule rather than duplicates.
     *
     * @param array<int,array<int,int>>|null $manualTeacherAssignments teacherId => [chapterId, ...]
     *
     * @throws RuntimeException when the plan has no macro plan or no bookable slots.
     */
    public function generate(int $planId, ?array $manualTeacherAssignments = null): array
    {
        Log::info("MesoPlan: generating for plan_id={$planId}");

        $plan      = $this->loadPlan($planId);
        $macroPlan = $this->intel->decodeJsonPublic($plan->macro_plan_json);

        if (!is_array($macroPlan) || !isset($macroPlan['chapter_schedule'])) {
            throw new RuntimeException("Plan {$planId} has no macro plan yet. Run the macro plan (Phase 1) first.");
        }

        [$calendar, $schoolData] = $this->rebuildCalendar($plan);

        if (!$calendar) {
            throw new RuntimeException('No bookable teaching slots found for this term - check the timetable, holidays and exam dates.');
        }

        $periodDuration = (int) ($plan->period_duration_min ?: LessonIntelligenceService::DEFAULT_PERIOD_DURATION_MIN);

        [$contentInstId, $mappedStd, $mappedSub] = $this->intel->resolveContentSource(
            (int) $plan->standard_id,
            (int) $plan->subject_id
        );

        // Concepts grouped by chapter, in teaching order.
        $chapterConcepts = [];
        $conceptRows = DB::table('lms_concept as c')
            ->join('chapter_master as cm', 'c.chapter_id', '=', 'cm.id')
            ->where('cm.sub_institute_id', $contentInstId)
            ->where('cm.standard_id', $mappedStd)
            ->where('cm.subject_id', $mappedSub)
            ->where('cm.availability', 1)
            ->orderBy('cm.sort_order')
            ->orderBy('c.id')
            ->get(['c.id', 'c.name', 'c.estimated_mastery_minutes', 'cm.id as chapter_id', 'cm.chapter_name']);

        foreach ($conceptRows as $r) {
            $chapterConcepts[(int) $r->chapter_id][] = [
                'id'                        => (int) $r->id,
                'name'                      => $r->name,
                'estimated_mastery_minutes' => (int) ($r->estimated_mastery_minutes ?: 0),
            ];
        }

        // Every slot needs an owning teacher; fall back to the subject's primary.
        foreach ($calendar as $i => $slot) {
            if (empty($slot['teacher_id'])) {
                $calendar[$i]['teacher_id'] = $schoolData['teacher_id'];
            }
        }

        $teacherIds = array_values(array_unique(array_filter(array_column($calendar, 'teacher_id'))));
        if (!$teacherIds) {
            throw new RuntimeException('No teacher is assigned to this subject in the timetable, so periods cannot be created.');
        }

        $teacherCalendars = [];
        foreach ($teacherIds as $tid) {
            $teacherCalendars[$tid] = array_values(array_filter($calendar, fn ($s) => (int) $s['teacher_id'] === $tid));
        }

        $teacherAssignments = $this->assignChaptersToTeachers(
            $macroPlan['chapter_schedule'],
            $teacherIds,
            $teacherCalendars,
            $manualTeacherAssignments
        );

        $periodsInserted  = 0;
        $conceptsInserted = 0;
        $now              = now();

        $subInstituteId = (int) $plan->sub_institute_id;

        DB::transaction(function () use (
            $planId, $subInstituteId, $teacherIds, $teacherCalendars, $teacherAssignments,
            $chapterConcepts, $periodDuration, $now, &$periodsInserted, &$conceptsInserted
        ) {
            // Rebuild from scratch - the FK cascade clears the concept links.
            DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS)
                ->where('lms_intelligence_lesson_plans_id', $planId)
                ->delete();

            foreach ($teacherIds as $tid) {
                $tCalendar = $teacherCalendars[$tid];
                $slotIdx   = 0;
                $streak    = 0;

                foreach ($teacherAssignments[$tid] as $chSchedule) {
                    $chId    = $chSchedule['chapter_id'];
                    $chName  = $chSchedule['chapter_name'];
                    $alloc   = (int) $chSchedule['allocated_periods'];
                    $chSlots = array_slice($tCalendar, $slotIdx, $alloc);
                    $slotIdx += $alloc;

                    if (!$chSlots) {
                        continue;
                    }

                    // The synthetic "Assessment Preparation" block carries id -1.
                    if ($chId === null || $chId < 0) {
                        foreach ($chSlots as $slot) {
                            $this->insertPeriod($planId, $slot, $tid, $chId, $chName, null, null, 'buffer', $periodDuration, $now, $subInstituteId);
                            $periodsInserted++;
                        }
                        continue;
                    }

                    $cList          = $chapterConcepts[$chId] ?? [];
                    $conceptIdx     = 0;
                    $conceptRemain  = $cList ? ($cList[0]['estimated_mastery_minutes'] ?: $periodDuration) : 0;

                    foreach ($chSlots as $slot) {
                        $periodConcepts     = [];
                        $primaryConceptId   = null;
                        $primaryConceptName = null;

                        if ($streak >= self::REVISION_AFTER_CONSECUTIVE) {
                            $periodType         = 'revision';
                            $primaryConceptName = 'Doubt Clearing & Review';
                            $streak             = 0;

                            if ($conceptIdx > 0 && $cList) {
                                $lastConcept        = $cList[$conceptIdx - 1];
                                $primaryConceptId   = $lastConcept['id'];
                                $primaryConceptName = 'Review: ' . $lastConcept['name'];
                            }
                        } else {
                            $periodType = 'teaching';
                            $streak++;
                            $slotRemain = $periodDuration;

                            // Fill the period with as many concepts as fit.
                            while ($slotRemain > 0 && $conceptIdx < count($cList)) {
                                $concept = $cList[$conceptIdx];
                                $usedMin = min($slotRemain, $conceptRemain);

                                if ($primaryConceptId === null) {
                                    $primaryConceptId   = $concept['id'];
                                    $primaryConceptName = $concept['name'];
                                }

                                $totalMin = $concept['estimated_mastery_minutes'] ?: $periodDuration;

                                $periodConcepts[] = [
                                    'concept_id'       => $concept['id'],
                                    'concept_name'     => $concept['name'],
                                    'is_primary'       => $primaryConceptId === $concept['id'],
                                    'coverage_percent' => $totalMin > 0 ? (int) ($usedMin / $totalMin * 100) : 100,
                                ];

                                $slotRemain    -= $usedMin;
                                $conceptRemain -= $usedMin;

                                if ($conceptRemain <= 0) {
                                    $conceptIdx++;
                                    if ($conceptIdx < count($cList)) {
                                        $conceptRemain = $cList[$conceptIdx]['estimated_mastery_minutes'] ?: $periodDuration;
                                    }
                                }
                            }

                            // Chapter ran out of concepts but still has slots -
                            // spend the remainder revising the last one.
                            if (!$periodConcepts) {
                                $periodType = 'revision';
                                if ($cList) {
                                    $lastConcept        = $cList[count($cList) - 1];
                                    $primaryConceptId   = $lastConcept['id'];
                                    $primaryConceptName = $lastConcept['name'];
                                    $periodConcepts[]   = [
                                        'concept_id'       => $lastConcept['id'],
                                        'concept_name'     => $lastConcept['name'],
                                        'is_primary'       => true,
                                        'coverage_percent' => 100,
                                    ];
                                }
                            }
                        }

                        $periodRowId = $this->insertPeriod(
                            $planId, $slot, $tid, $chId, $chName,
                            $primaryConceptId, $primaryConceptName,
                            $periodType, $periodDuration, $now, $subInstituteId
                        );
                        $periodsInserted++;

                        foreach ($periodConcepts as $pc) {
                            DB::table(LessonIntelligenceService::TBL_PLAN_CONCEPTS)->insert([
                                'lms_lesson_plan_periods_id' => $periodRowId,
                                'sub_institute_id'           => $subInstituteId,
                                'concept_id'                 => $pc['concept_id'],
                                'concept_name'               => $pc['concept_name'],
                                'is_primary'                 => $pc['is_primary'],
                                'coverage_percent'           => $pc['coverage_percent'],
                                'created_at'                 => $now,
                            ]);
                            $conceptsInserted++;
                        }
                    }
                }
            }
        });

        return [
            'status'                   => 'success',
            'plan_id'                  => $planId,
            'periods_created'          => $periodsInserted,
            'concept_mappings_created' => $conceptsInserted,
        ];
    }

    /**
     * Hand each chapter to a teacher. Manual mappings win; anything the user did
     * not map (and everything, when no mapping is supplied) goes to whichever
     * teacher has the most unallocated calendar left.
     *
     * @return array<int,array<int,array>>
     */
    private function assignChaptersToTeachers(
        array $chapterSchedule,
        array $teacherIds,
        array $teacherCalendars,
        ?array $manual
    ): array {
        $assignments = array_fill_keys($teacherIds, []);

        $leastLoaded = function () use ($teacherIds, $teacherCalendars, &$assignments) {
            $best      = $teacherIds[0];
            $bestSpare = PHP_INT_MIN;

            foreach ($teacherIds as $tid) {
                $spare = count($teacherCalendars[$tid])
                    - array_sum(array_column($assignments[$tid], 'allocated_periods'));
                if ($spare > $bestSpare) {
                    $bestSpare = $spare;
                    $best      = $tid;
                }
            }

            return $best;
        };

        foreach ($chapterSchedule as $ch) {
            $placed = false;

            if ($manual) {
                foreach ($manual as $tid => $chapterIds) {
                    $tid = (int) $tid;
                    if (isset($assignments[$tid]) && in_array($ch['chapter_id'], $chapterIds, false)) {
                        $assignments[$tid][] = $ch;
                        $placed = true;
                        break;
                    }
                }
            }

            if (!$placed) {
                $assignments[$leastLoaded()][] = $ch;
            }
        }

        return $assignments;
    }

    /** @return int the new period row id */
    private function insertPeriod(
        int $planId,
        array $slot,
        int $teacherId,
        $chapterId,
        ?string $chapterName,
        ?int $primaryConceptId,
        ?string $primaryConceptName,
        string $periodType,
        int $duration,
        $now,
        int $subInstituteId
    ): int {
        return (int) DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS)->insertGetId([
            'lms_intelligence_lesson_plans_id' => $planId,
            // Denormalised from the plan so institute-scoped reads can filter
            // the row directly instead of joining back up the chain.
            'sub_institute_id'                 => $subInstituteId,
            'scheduled_date'                   => $slot['date']->toDateString(),
            'week_day'                         => $slot['week_day'],
            'week_number'                      => $slot['week_number'],
            'period_id'                        => $slot['period_id'],
            'period_slot'                      => $slot['period_slot'],
            'teacher_id'                       => $teacherId,
            'chapter_id'                       => $chapterId,
            'chapter_name'                     => $chapterName,
            'primary_concept_id'               => $primaryConceptId,
            'primary_concept_name'             => $primaryConceptName,
            'period_type'                      => $periodType,
            'planned_duration_min'             => $duration,
            'status'                           => 'not_started',
            'created_at'                       => $now,
            'updated_at'                       => $now,
        ]);
    }

    private function loadPlan(int $planId): object
    {
        $plan = DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)->where('id', $planId)->first();

        if (!$plan) {
            throw new RuntimeException("Lesson plan {$planId} not found.");
        }

        return $plan;
    }

    /**
     * Rebuild the exact slot list Phase 1 saw, so period rows land on the same
     * dates the macro plan was sized against.
     *
     * @return array{0:array<int,array>,1:array}
     */
    private function rebuildCalendar(object $plan): array
    {
        $divisionId = $plan->division_id !== null ? (int) $plan->division_id : null;

        // The plan's syear is the year it is displayed under, which is not
        // necessarily the year its timetable lives in - ask which year can
        // actually be scheduled from.
        $schedulingYear = $this->intel->resolveSchedulingYear(
            (int) $plan->sub_institute_id,
            (int) $plan->standard_id,
            (int) $plan->subject_id,
            (int) $plan->syear,
            $divisionId
        );

        $schoolData = $this->intel->assembleSchoolData(
            (int) $plan->sub_institute_id,
            (int) $plan->standard_id,
            (int) $plan->subject_id,
            $schedulingYear,
            $divisionId
        );

        $holidaySet = [];
        foreach ($schoolData['holidays'] as $h) {
            if (($h['date'] ?? null) instanceof CarbonImmutable) {
                $holidaySet[$h['date']->toDateString()] = true;
            }
        }

        $examSet = [];
        foreach ($schoolData['exam_dates'] as $e) {
            if (($e['date'] ?? null) instanceof CarbonImmutable) {
                $examSet[$e['date']->toDateString()] = true;
            }
        }

        $calendar = $this->intel->buildTeachingCalendar(
            CarbonImmutable::parse($plan->term_start_date)->startOfDay(),
            CarbonImmutable::parse($plan->term_end_date)->startOfDay(),
            $schoolData['weekly_schedule'],
            $holidaySet,
            $examSet,
            $schoolData['has_saturday']
        );

        return [$calendar, $schoolData];
    }
}
