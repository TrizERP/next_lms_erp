<?php

namespace App\Services\Mcp;

use App\Domain\K12\AcademicRisk\HomeworkCompletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What is happening in a class: virtual classes and homework, on one timeline.
 *
 * The estate's own activity stream builds this from two tables and renders it into a
 * Blade view through the session. Neither the view nor the session is reachable from a
 * governed tool call, so the timeline is rebuilt here from the same two sources.
 *
 * Scoping is by standard, not by student, because that is how both tables store it —
 * `lms_virtual_classroom` has no student column at all. A question about one student is
 * answered by resolving their standard first, and the result says so, because "your
 * class has three sessions this week" and "you have three sessions" are different claims
 * and only one of them is supported by these rows.
 */
class LmsActivityService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function timeline(McpRequestContext $context, array $filters): array
    {
        $standardId = $this->resolveStandard($context, $filters);

        if ($standardId === null) {
            return [
                'count' => 0,
                'activities' => [],
                'note' => 'No class was identified. Pass a standard_id, or a student_id that has an '
                    . 'enrolment for the current academic year.',
            ];
        }

        $ahead = min(max((int) ($filters['days_ahead'] ?? 7), 0), 90);
        $back = min(max((int) ($filters['days_back'] ?? 7), 0), 90);
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $from = now()->subDays($back)->toDateString();
        $to = now()->addDays($ahead)->toDateString();
        $today = now()->toDateString();

        $activities = [
            ...$this->virtualClasses($context, $standardId, $from, $to),
            ...$this->homework($context, $standardId, $from, $to, $filters),
        ];

        usort($activities, static fn (array $a, array $b) => [$a['date'], $a['title']] <=> [$b['date'], $b['title']]);

        foreach ($activities as &$activity) {
            $activity['when'] = match (true) {
                $activity['date'] === $today => 'today',
                $activity['date'] > $today => 'upcoming',
                default => 'past',
            };
        }

        unset($activity);

        $activities = array_slice($activities, 0, $limit);

        return [
            'standard_id' => $standardId,
            'standard_name' => $this->standardName($context, $standardId),
            'from' => $from,
            'to' => $to,
            'today' => $today,
            'count' => count($activities),
            'by_when' => array_count_values(array_column($activities, 'when')),
            'activities' => $activities,
            'note' => 'Scoped to the class, not the individual — these records are stored per '
                . 'standard, so they describe what the class has rather than what one student has.',
        ];
    }

    // ---------------------------------------------------------------- sources

    /**
     * @return array<int, array<string, mixed>>
     */
    private function virtualClasses(McpRequestContext $context, int $standardId, string $from, string $to): array
    {
        if (! Schema::hasTable('lms_virtual_classroom')) {
            return [];
        }

        $query = DB::table('lms_virtual_classroom as v')
            ->leftJoin('sub_std_map as m', function ($join) {
                $join->on('m.subject_id', '=', 'v.subject_id')
                    ->on('m.standard_id', '=', 'v.standard_id');
            })
            ->where('v.sub_institute_id', $context->selectedInstituteId)
            ->where('v.standard_id', $standardId)
            ->whereBetween('v.event_date', [$from, $to]);

        if ($context->academicYear !== null) {
            $query->where('v.syear', $context->academicYear);
        }

        return $query
            ->orderBy('v.event_date')
            ->get([
                'v.id', 'v.room_name', 'v.description', 'v.event_date',
                'v.from_time', 'v.to_time', 'v.url', 'v.status', 'm.display_name as subject_name',
            ])
            ->map(static fn ($row) => [
                'kind' => 'virtual_class',
                'id' => (int) $row->id,
                'title' => $row->room_name,
                'subject' => $row->subject_name,
                'date' => $row->event_date,
                'from_time' => $row->from_time,
                'to_time' => $row->to_time,
                // The join URL is intentionally not returned: it is a live meeting
                // credential, and a chat transcript is not where it belongs.
                'has_link' => ! empty($row->url),
                'description' => $row->description,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function homework(
        McpRequestContext $context,
        int $standardId,
        string $from,
        string $to,
        array $filters
    ): array {
        if (! Schema::hasTable('homework')) {
            return [];
        }

        $query = DB::table('homework as h')
            ->leftJoin('subject as s', 's.id', '=', 'h.subject_id')
            ->where('h.sub_institute_id', $context->selectedInstituteId)
            ->where('h.standard_id', $standardId)
            ->whereBetween('h.date', [$from, $to]);

        if ($context->academicYear !== null && Schema::hasColumn('homework', 'syear')) {
            $query->where('h.syear', $context->academicYear);
        }

        // A homework row exists per student, so an assignment set for a class appears
        // once per child. Narrowing to one student is the only way to make the count
        // mean "pieces of work" rather than "pieces of work times class size".
        if (! empty($filters['student_id'])) {
            $query->where('h.student_id', (int) $filters['student_id']);
        }

        return $query
            ->orderBy('h.date')
            ->limit(200)
            ->get([
                'h.id', 'h.title', 'h.date', 'h.submission_date', 'h.completion_status',
                'h.student_id', 's.subject_name',
            ])
            ->map(static fn ($row) => [
                'kind' => 'homework',
                'id' => (int) $row->id,
                'title' => $row->title,
                'subject' => $row->subject_name,
                'date' => $row->date,
                'from_time' => null,
                'to_time' => null,
                'student_id' => $row->student_id ? (int) $row->student_id : null,
                'status' => HomeworkCompletion::label($row),
            ])
            ->all();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveStandard(McpRequestContext $context, array $filters): ?int
    {
        if (! empty($filters['standard_id'])) {
            return (int) $filters['standard_id'];
        }

        $studentId = (int) ($filters['student_id'] ?? 0);

        if ($studentId <= 0 || ! Schema::hasTable('tblstudent_enrollment')) {
            return null;
        }

        $query = DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('student_id', $studentId);

        if ($context->academicYear !== null) {
            $query->where('syear', $context->academicYear);
        }

        $standardId = $query->orderByDesc('id')->value('standard_id');

        return $standardId ? (int) $standardId : null;
    }

    private function standardName(McpRequestContext $context, int $standardId): ?string
    {
        if (! Schema::hasTable('standard')) {
            return null;
        }

        return DB::table('standard')
            ->where('id', $standardId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->value('name');
    }
}
