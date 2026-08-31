<?php

namespace App\Services\Mcp;

use App\Domain\K12\AcademicRisk\HomeworkCompletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Homework, and what has come back.
 *
 * Completion is decided by HomeworkCompletion, the same rule the missed-assignment
 * detector uses — so a chat reply and a risk flag can never disagree about whether a
 * child handed something in.
 *
 * "Overdue" is deliberately narrower than "not submitted": work due next week that has
 * not arrived is not late, and counting it as such would inflate every summary.
 */
class HomeworkInsightService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('homework')) {
            return ['count' => 0, 'items' => [], 'note' => 'Homework is not recorded in this estate.'];
        }

        $days = min(max((int) ($filters['days'] ?? 30), 1), 365);
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $since = now()->subDays($days)->toDateString();

        $query = DB::table('homework as h')
            ->leftJoin('tblstudent as s', 's.id', '=', 'h.student_id')
            ->leftJoin('subject as sub', 'sub.id', '=', 'h.subject_id')
            ->leftJoin('standard as st', 'st.id', '=', 'h.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'h.division_id')
            ->where('h.sub_institute_id', $context->selectedInstituteId)
            ->where('h.date', '>=', $since);

        if ($context->academicYear !== null && Schema::hasColumn('homework', 'syear')) {
            $query->where('h.syear', $context->academicYear);
        }

        foreach ([
            'student_id' => 'h.student_id',
            'subject_id' => 'h.subject_id',
            'standard_id' => 'h.standard_id',
            'division_id' => 'h.division_id',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $rows = $query
            ->selectRaw(
                "h.id, h.student_id, h.subject_id, h.title, h.date, h.submission_date,
                 h.completion_status, h.type,
                 CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                 sub.subject_name, st.name AS standard_name, d.name AS division_name"
            )
            ->orderByDesc('h.date')
            ->limit($limit)
            ->get();

        $today = now()->toDateString();
        $items = [];
        $missed = 0;
        $overdue = 0;

        foreach ($rows as $row) {
            $isMissed = HomeworkCompletion::isMissed($row);
            $isOverdue = $isMissed && $row->date !== null && $row->date < $today;

            $missed += $isMissed ? 1 : 0;
            $overdue += $isOverdue ? 1 : 0;

            $items[] = [
                'homework_id' => (int) $row->id,
                'title' => $row->title,
                'student_id' => $row->student_id ? (int) $row->student_id : null,
                'student_name' => trim((string) $row->student_name) ?: null,
                'subject' => $row->subject_name,
                'standard_name' => $row->standard_name,
                'division_name' => $row->division_name,
                'due_date' => $row->date,
                'submission_date' => $row->submission_date,
                'status' => HomeworkCompletion::label($row),
                'is_overdue' => $isOverdue,
            ];
        }

        // Filtering after the completion rule rather than in SQL: the rule reads two
        // columns and tolerates a school's own vocabulary, which a WHERE clause cannot.
        $only = $filters['status'] ?? null;

        if ($only === 'overdue') {
            $items = array_values(array_filter($items, static fn (array $i) => $i['is_overdue']));
        } elseif ($only === 'pending') {
            $items = array_values(array_filter($items, static fn (array $i) => $i['status'] !== 'submitted' && $i['status'] !== 'completed'));
        } elseif ($only === 'submitted') {
            $items = array_values(array_filter($items, static fn (array $i) => $i['status'] === 'submitted' || $i['status'] === 'completed'));
        }

        return [
            'window_days' => $days,
            'since' => $since,
            'count' => count($items),
            'not_submitted' => $missed,
            'overdue' => $overdue,
            'items' => $items,
            'rule' => 'An item counts as submitted from its completion status where the school sets '
                . 'one, otherwise from the presence of a submission date. Only work whose due date '
                . 'has passed is called overdue.',
        ];
    }
}
