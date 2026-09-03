<?php

namespace App\Services\LessonIntelligence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Phase 1 - Macro plan.
 *
 * Distributes a subject's chapters across the teaching weeks of each term,
 * proportionally to how many minutes their concepts actually need, and stores
 * the result as one lms_intelligence_lesson_plans row per term (capacity
 * numbers in columns, the week-by-week schedule in macro_plan_json).
 *
 * Pure arithmetic - no LLM call, so regenerating is free.
 */
class MacroPlanService
{
    public function __construct(private LessonIntelligenceService $intel)
    {
    }

    /**
     * Split the available slots between the chapters.
     *
     * Preference order: mastery minutes -> concept count -> equal split, with a
     * floor of one period per chapter. A slice of the term (up to 5 periods) is
     * held back as an "Assessment Preparation & Revision" block.
     *
     * @return array{0:array<int,array>,1:int} [chaptersWithPeriods, bufferPeriods]
     */
    public function distributeChaptersToSlots(array $chapters, int $totalSlots, int $periodDuration): array
    {
        if (!$chapters || $totalSlots <= 0) {
            return [[], 0];
        }

        $totalMinutes  = array_sum(array_column($chapters, 'total_mastery_minutes'));
        $totalConcepts = array_sum(array_column($chapters, 'concept_count'));

        $bufferPeriods = min(5, intdiv($totalSlots, 10));
        $teachingSlots = $totalSlots - $bufferPeriods;

        $result = [];

        if ($totalMinutes > 0) {
            foreach ($chapters as $ch) {
                $chMinutes  = ($ch['total_mastery_minutes'] ?: 0) ?: $periodDuration;
                $rawPeriods = $chMinutes / $totalMinutes * $teachingSlots;
                $result[]   = $ch + [
                    'allocated_periods'  => max(1, (int) round($rawPeriods)),
                    'estimated_minutes'  => $chMinutes,
                    'periods_by_mastery' => $rawPeriods,
                ];
            }
        } elseif ($totalConcepts > 0) {
            foreach ($chapters as $ch) {
                $chConcepts = ($ch['concept_count'] ?: 0) ?: 1;
                $rawPeriods = $chConcepts / $totalConcepts * $teachingSlots;
                $result[]   = $ch + [
                    'allocated_periods'  => max(1, (int) round($rawPeriods)),
                    'estimated_minutes'  => $chConcepts * $periodDuration,
                    'periods_by_mastery' => $rawPeriods,
                ];
            }
        } else {
            // No concept data at all - fall back to an even split.
            $perChapter = max(1, intdiv($teachingSlots, count($chapters)));
            $remainder  = $teachingSlots - ($perChapter * count($chapters));

            foreach (array_values($chapters) as $i => $ch) {
                $result[] = $ch + [
                    'allocated_periods'  => $perChapter + ($i < $remainder ? 1 : 0),
                    'estimated_minutes'  => 0,
                    'periods_by_mastery' => 0,
                ];
            }
        }

        // Rounding leaves the total slightly off; settle the difference against
        // the largest chapter, then restore curriculum order.
        $allocated = array_sum(array_column($result, 'allocated_periods'));
        $diff      = $teachingSlots - $allocated;
        if ($diff !== 0 && $result) {
            usort($result, fn ($a, $b) => $b['allocated_periods'] <=> $a['allocated_periods']);
            $result[0]['allocated_periods'] = max(1, $result[0]['allocated_periods'] + $diff);
            usort($result, fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));
        }

        if ($bufferPeriods > 0) {
            $result[] = [
                'chapter_id'         => -1,
                'chapter_name'       => 'Assessment Preparation & Revision',
                'sort_order'         => 9999,
                'allocated_periods'  => $bufferPeriods,
                'concept_count'      => 0,
                'estimated_minutes'  => $bufferPeriods * $periodDuration,
                'periods_by_mastery' => $bufferPeriods,
            ];
        }

        return [$result, $bufferPeriods];
    }

    /**
     * Walk the calendar chronologically handing slots to chapters in order,
     * producing both the per-chapter date span and the per-week breakdown the
     * UI renders.
     */
    public function assignChaptersToWeeks(array $chaptersWithPeriods, array $teachingCalendar, int $bufferPeriods): array
    {
        if (!$chaptersWithPeriods || !$teachingCalendar) {
            return ['chapter_schedule' => [], 'week_plan' => [], 'summary' => []];
        }

        $weeks = [];
        foreach ($teachingCalendar as $slot) {
            $weeks[$slot['week_number']][] = $slot;
        }

        $totalSlots       = count($teachingCalendar);
        $slotIndex        = 0;
        $chapterSchedule  = [];

        foreach ($chaptersWithPeriods as $ch) {
            $chPeriods = $ch['allocated_periods'];
            if ($slotIndex >= $totalSlots) {
                break;
            }

            $startSlot = $slotIndex;
            $endSlot   = min($slotIndex + $chPeriods - 1, $totalSlots - 1);

            $chapterSchedule[] = [
                'chapter_id'        => $ch['chapter_id'],
                'chapter_name'      => $ch['chapter_name'],
                'sort_order'        => $ch['sort_order'] ?? null,
                'allocated_periods' => $chPeriods,
                'concept_count'     => $ch['concept_count'] ?? 0,
                'estimated_minutes' => $ch['estimated_minutes'] ?? 0,
                'start_date'        => $teachingCalendar[$startSlot]['date']->toDateString(),
                'end_date'          => $teachingCalendar[$endSlot]['date']->toDateString(),
                'start_week'        => $teachingCalendar[$startSlot]['week_number'],
                'end_week'          => $teachingCalendar[$endSlot]['week_number'],
                'weeks_span'        => $teachingCalendar[$endSlot]['week_number']
                    - $teachingCalendar[$startSlot]['week_number'] + 1,
            ];

            $slotIndex += $chPeriods;
        }

        // Second pass: attribute each individual slot to its week so the UI can
        // show "chapter X gets 3 periods in week 4".
        $weekPlan = [];
        $slotIdx  = 0;

        $touchWeek = function (int $weekNumber) use (&$weekPlan, $weeks): int {
            foreach ($weekPlan as $i => $w) {
                if ($w['week_number'] === $weekNumber) {
                    return $i;
                }
            }

            $weekDates = array_map(fn ($s) => $s['date']->toDateString(), $weeks[$weekNumber] ?? []);
            $weekPlan[] = [
                'week_number' => $weekNumber,
                'start_date'  => $weekDates ? min($weekDates) : null,
                'end_date'    => $weekDates ? max($weekDates) : null,
                'total_slots' => count($weeks[$weekNumber] ?? []),
                'chapters'    => [],
            ];

            return count($weekPlan) - 1;
        };

        foreach ($chapterSchedule as $chInfo) {
            for ($i = 0; $i < $chInfo['allocated_periods']; $i++) {
                if ($slotIdx >= $totalSlots) {
                    break;
                }

                $wi    = $touchWeek($teachingCalendar[$slotIdx]['week_number']);
                $found = false;

                foreach ($weekPlan[$wi]['chapters'] as $ci => $c) {
                    if ($c['chapter_id'] === $chInfo['chapter_id']) {
                        $weekPlan[$wi]['chapters'][$ci]['periods_this_week']++;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $weekPlan[$wi]['chapters'][] = [
                        'chapter_id'        => $chInfo['chapter_id'],
                        'chapter_name'      => $chInfo['chapter_name'],
                        'periods_this_week' => 1,
                    ];
                }

                $slotIdx++;
            }
        }

        // Anything left over after the chapters are placed is free revision time.
        for ($i = $slotIdx; $i < $totalSlots; $i++) {
            $wi    = $touchWeek($teachingCalendar[$i]['week_number']);
            $found = false;

            foreach ($weekPlan[$wi]['chapters'] as $ci => $c) {
                if (!empty($c['is_buffer'])) {
                    $weekPlan[$wi]['chapters'][$ci]['periods_this_week']++;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $weekPlan[$wi]['chapters'][] = [
                    'chapter_id'        => null,
                    'chapter_name'      => 'Buffer / Revision',
                    'periods_this_week' => 1,
                    'is_buffer'         => true,
                ];
            }
        }

        return [
            'chapter_schedule' => $chapterSchedule,
            'week_plan'        => $weekPlan,
            'summary'          => [
                'total_teaching_weeks'   => count($weekPlan),
                'total_chapters'         => count($chapterSchedule),
                'total_teaching_periods' => array_sum(array_column($chapterSchedule, 'allocated_periods')),
                'buffer_periods'         => $bufferPeriods,
                'total_slots_used'       => $totalSlots,
            ],
        ];
    }

    /**
     * Generate (or refresh) the macro plan for every term of a school year.
     *
     * Existing plans are left alone unless $force is set, so a teacher who has
     * already started delivering a term is not silently rescheduled.
     *
     * @throws RuntimeException when the school has no terms, no timetable, or the
     *                          subject has no chapters anywhere.
     */
    public function generate(
        int $subInstituteId,
        int $standardId,
        int $subjectId,
        int $syear,
        ?int $divisionId = null,
        bool $force = false,
        ?int $storeAsYear = null
    ): array {
        // Reading and recording can sit on different years. Institute 1 keeps its
        // terms, timetable and holidays under an earlier year while its lesson
        // plans are recorded under the year the calendar displays, so the plan
        // row is stamped with $storeAsYear while every lookup still uses $syear.
        $planYear = $storeAsYear ?? $syear;
        Log::info("MacroPlan: generating inst={$subInstituteId} std={$standardId} sub={$subjectId} year={$syear}");

        $schoolData = $this->intel->assembleSchoolData($subInstituteId, $standardId, $subjectId, $syear, $divisionId);

        if (!$schoolData['terms']) {
            throw new RuntimeException("No terms found for institute {$subInstituteId} in {$syear}. Add them under Academic Year first.");
        }

        if ($schoolData['periods_per_week'] === 0) {
            throw new RuntimeException(
                $this->describeTimetableGap($subInstituteId, $standardId, $subjectId, $syear, $divisionId)
            );
        }

        [$contentInstId, $mappedStd, $mappedSub] = $this->intel->resolveContentSource($standardId, $subjectId);
        $chapters = $this->intel->getChaptersForSubject($contentInstId, $mappedStd, $mappedSub);

        if (!$chapters) {
            // Report the coordinates actually queried - they differ from the
            // school's own whenever the name mapping resolved elsewhere.
            throw new RuntimeException(
                "No chapters found for institute {$contentInstId}, standard {$mappedStd}, subject {$mappedSub} "
                . "(school standard {$standardId}, subject {$subjectId})."
            );
        }

        $stdName = DB::table('standard')->where('id', $standardId)->value('name') ?: "Std {$standardId}";
        $subName = DB::table('subject')->where('id', $subjectId)->value('subject_name') ?: "Sub {$subjectId}";

        $holidaySet = $this->dateSetOf($schoolData['holidays']);
        $examSet    = $this->dateSetOf($schoolData['exam_dates']);
        $capacity   = $this->intel->computeCapacity($schoolData);

        $terms         = $schoolData['terms'];
        $totalTermDays = 0;
        foreach ($terms as $t) {
            if ($t['start_date'] instanceof CarbonImmutable && $t['end_date'] instanceof CarbonImmutable) {
                $totalTermDays += $t['start_date']->diffInDays($t['end_date']);
            }
        }

        $now         = now();
        $planResults = [];

        foreach ($terms as $termIdx => $term) {
            $termStart = $term['start_date'];
            $termEnd   = $term['end_date'];

            if (!$termStart instanceof CarbonImmutable || !$termEnd instanceof CarbonImmutable) {
                continue;
            }
            if ($termEnd->lessThanOrEqualTo($termStart)) {
                continue;
            }

            $termCapacity = $capacity[$termIdx] ?? null;

            $calendar   = $this->intel->buildTeachingCalendar(
                $termStart,
                $termEnd,
                $schoolData['weekly_schedule'],
                $holidaySet,
                $examSet,
                $schoolData['has_saturday']
            );
            $totalSlots = count($calendar);

            if ($totalSlots === 0) {
                continue;
            }

            // Multi-term years get a proportional slice of the chapter list, so
            // term 2 starts where term 1 left off instead of repeating it.
            if (count($terms) > 1 && $totalTermDays > 0) {
                $chStart = (int) (count($chapters) * $this->termFractionUpTo($terms, $termIdx, $totalTermDays));
                $chEnd   = (int) (count($chapters) * $this->termFractionUpTo($terms, $termIdx + 1, $totalTermDays));
                $termChapters = $chEnd > $chStart ? array_slice($chapters, $chStart, $chEnd - $chStart) : $chapters;
            } else {
                $termChapters = $chapters;
            }

            [$chaptersWithPeriods, $bufferPeriods] = $this->distributeChaptersToSlots(
                $termChapters,
                $totalSlots,
                $schoolData['period_duration_min']
            );

            $macroPlan = $this->assignChaptersToWeeks($chaptersWithPeriods, $calendar, $bufferPeriods);

            $termTitle = $term['title'] ?? ('Term ' . ($termIdx + 1));
            $planTitle = "{$stdName} - {$subName} - {$termTitle} ({$planYear})";

            $existing = DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)
                ->where('sub_institute_id', $subInstituteId)
                ->where('syear', $planYear)
                ->where('term_id', $term['term_id'])
                ->where('standard_id', $standardId)
                ->where('subject_id', $subjectId)
                ->where(function ($q) use ($divisionId) {
                    $divisionId === null
                        ? $q->whereNull('division_id')
                        : $q->where('division_id', $divisionId);
                })
                ->first(['id', 'generation_status']);

            if ($existing && !$force) {
                $planResults[] = [
                    'term_id'           => $term['term_id'],
                    'term_title'        => $term['title'] ?? null,
                    'status'            => 'already_exists',
                    'plan_id'           => (int) $existing->id,
                    'generation_status' => $existing->generation_status,
                ];
                continue;
            }

            $payload = [
                'plan_title'          => $planTitle,
                'term_start_date'     => $termStart->toDateString(),
                'term_end_date'       => $termEnd->toDateString(),
                'total_teaching_days' => $termCapacity['total_teaching_periods'] ?? $totalSlots,
                'total_periods'       => $totalSlots,
                'periods_per_week'    => $schoolData['periods_per_week'],
                'period_duration_min' => $schoolData['period_duration_min'],
                'total_teaching_min'  => $totalSlots * $schoolData['period_duration_min'],
                'total_required_min'  => array_sum(array_column($chaptersWithPeriods, 'estimated_minutes')),
                'buffer_percent'      => $termCapacity['buffer_percent'] ?? 0,
                'holidays_count'      => $termCapacity['holidays_in_term'] ?? 0,
                'exam_days_count'     => $termCapacity['exam_days_in_term'] ?? 0,
                'macro_plan_json'     => json_encode($macroPlan, JSON_UNESCAPED_UNICODE),
                'generation_status'   => 'completed',
                'generation_progress' => 100,
                'generation_error'    => null,
                'generated_at'        => $now,
                'generated_by'        => 'macro_plan_v1',
                'updated_at'          => $now,
            ];

            if ($existing) {
                DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)
                    ->where('id', $existing->id)
                    ->update($payload);
                $planId = (int) $existing->id;
            } else {
                $planId = (int) DB::table(LessonIntelligenceService::TBL_LESSON_PLANS)->insertGetId($payload + [
                    'sub_institute_id' => $subInstituteId,
                    'syear'            => $planYear,
                    'term_id'          => $term['term_id'],
                    'standard_id'      => $standardId,
                    'subject_id'       => $subjectId,
                    'division_id'      => $divisionId,
                    'created_at'       => $now,
                ]);
            }

            $planResults[] = [
                'term_id'    => $term['term_id'],
                'term_title' => $term['title'] ?? null,
                'status'     => 'generated',
                'plan_id'    => $planId,
                'macro_plan' => $macroPlan,
                'capacity'   => $termCapacity,
            ];
        }

        return [
            'status'           => 'success',
            'sub_institute_id' => $subInstituteId,
            'standard_id'      => $standardId,
            'subject_id'       => $subjectId,
            'syear'            => $planYear,
            'source_syear'     => $syear,
            'total_chapters'   => count($chapters),
            'terms_processed'  => count($planResults),
            'plans'            => $planResults,
        ];
    }

    /**
     * Explain a missing timetable in terms of what the school actually has.
     *
     * "No timetable data" on its own leaves the user guessing which of the four
     * coordinates is wrong, so widen the search one axis at a time - division,
     * then year, then subject - and name the first thing that would work.
     */
    private function describeTimetableGap(
        int $subInstituteId,
        int $standardId,
        int $subjectId,
        int $syear,
        ?int $divisionId
    ): string {
        $stdName = DB::table('standard')->where('id', $standardId)->value('name') ?: "standard {$standardId}";
        $subName = DB::table('subject')->where('id', $subjectId)->value('subject_name') ?: "subject {$subjectId}";
        $what    = "{$subName} for {$stdName}";

        // Same standard + subject + year, only the division differs?
        if ($divisionId !== null) {
            $divisions = DB::table('timetable as t')
                ->join('period as p', 't.period_id', '=', 'p.id')
                ->leftJoin('division as d', 't.division_id', '=', 'd.id')
                ->where('t.sub_institute_id', $subInstituteId)
                ->where('t.standard_id', $standardId)
                ->where('t.subject_id', $subjectId)
                ->where('t.syear', $syear)
                ->distinct()
                ->pluck('d.name', 't.division_id');

            if ($divisions->isNotEmpty()) {
                return "No timetable for {$what} in {$syear} for the selected division. "
                    . 'It is timetabled for: ' . implode(', ', array_filter($divisions->all())) . '.';
            }
        }

        // Same standard + subject, different year?
        $years = DB::table('timetable as t')
            ->join('period as p', 't.period_id', '=', 'p.id')
            ->where('t.sub_institute_id', $subInstituteId)
            ->where('t.standard_id', $standardId)
            ->where('t.subject_id', $subjectId)
            ->distinct()
            ->orderByDesc('t.syear')
            ->pluck('t.syear');

        if ($years->isNotEmpty()) {
            return "No timetable for {$what} in {$syear}. "
                . 'It is timetabled in: ' . $years->implode(', ') . '. Switch the academic year to one of those.';
        }

        // Standard timetabled at all this year, just not this subject?
        $subjects = DB::table('timetable as t')
            ->join('period as p', 't.period_id', '=', 'p.id')
            ->join('subject as s', 't.subject_id', '=', 's.id')
            ->where('t.sub_institute_id', $subInstituteId)
            ->where('t.standard_id', $standardId)
            ->where('t.syear', $syear)
            ->distinct()
            ->limit(8)
            ->pluck('s.subject_name');

        if ($subjects->isNotEmpty()) {
            return "No timetable for {$subName} in {$stdName} for {$syear}. "
                . 'Subjects timetabled for this class: ' . $subjects->implode(', ') . '.';
        }

        // Nothing for this standard in this year - name the years that do work.
        $standardYears = DB::table('timetable as t')
            ->join('period as p', 't.period_id', '=', 'p.id')
            ->where('t.sub_institute_id', $subInstituteId)
            ->where('t.standard_id', $standardId)
            ->distinct()
            ->orderByDesc('t.syear')
            ->pluck('t.syear');

        if ($standardYears->isNotEmpty()) {
            return "No timetable for {$stdName} in {$syear}. "
                . 'This class is timetabled in: ' . $standardYears->implode(', ') . '.';
        }

        return "No timetable exists for {$stdName} at this institute, in {$syear} or any other year. "
            . 'A lesson plan can only be built once the class timetable has been entered.';
    }

    /** Share of the school year that the first $upTo terms account for. */
    private function termFractionUpTo(array $terms, int $upTo, int $totalTermDays): float
    {
        $sum = 0.0;
        for ($i = 0; $i < $upTo; $i++) {
            $start = $terms[$i]['start_date'] ?? null;
            $end   = $terms[$i]['end_date'] ?? null;
            if ($start instanceof CarbonImmutable && $end instanceof CarbonImmutable) {
                $sum += $start->diffInDays($end) / $totalTermDays;
            }
        }

        return $sum;
    }

    /** @return array<string,true> */
    private function dateSetOf(array $rows): array
    {
        $set = [];
        foreach ($rows as $row) {
            if (($row['date'] ?? null) instanceof CarbonImmutable) {
                $set[$row['date']->toDateString()] = true;
            }
        }

        return $set;
    }
}
