<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Circulars, as the `circular` table records them.
 *
 * One row is one circular published to one class: the title and the message the office
 * wrote, the date it carries, the type from `circular_type`, an optional attachment
 * filename, and the standard and division it was addressed to. A circular sent to six
 * classes is six rows sharing a title and a date, which is how `circularController`
 * writes it and how the existing report reads it back.
 *
 * SO "HOW MANY CIRCULARS" HAS TWO HONEST ANSWERS
 *
 * `count` is rows - publications, one per class. `distinct_circulars` groups by title and
 * date, which is what a person means when they ask how many circulars went out this term.
 * Both are reported and both are labelled, because reporting only the first turns one
 * notice to six classes into six notices.
 *
 * THE AUDIENCE IS A CLASS, NOT A LIST OF CHILDREN
 *
 * The table records the standard and division a circular was addressed to. It does not
 * record who opened it, and there is no read receipt anywhere in this schema. So nothing
 * here reports reach, readership or delivery, and a caller asking whether a family saw a
 * circular is told the record does not hold that rather than given a number that looks
 * like it does.
 *
 * `circular_type` IS ESTATE-WIDE
 *
 * It carries an id and a type name and no `sub_institute_id` - it is a shared vocabulary,
 * not tenant data. The circulars themselves are scoped by `sub_institute_id` and `syear`
 * from the caller's token, exactly as `circularController::getData()` scopes them.
 */
class CircularService
{
    /**
     * The circulars published, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('circular')) {
            return [
                'count' => 0,
                'circulars' => [],
                'note' => 'Circulars are not recorded in this estate.',
            ];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = $this->baseQuery($context);

        foreach (['standard_id' => 'c.standard_id', 'division_id' => 'c.division_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        if (! empty($filters['type_id'])) {
            $query->where('c.type', (int) $filters['type_id']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('c.date_', '>=', (string) $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('c.date_', '<=', (string) $filters['to_date']);
        }

        if (! empty($filters['search_text'])) {
            $needle = '%'.$filters['search_text'].'%';
            $query->where(function ($inner) use ($needle) {
                $inner->where('c.title', 'like', $needle)->orWhere('c.message', 'like', $needle);
            });
        }

        if (! empty($filters['with_attachment'])) {
            $query->whereNotNull('c.file_name')->where('c.file_name', '!=', '');
        }

        // Both totals are taken before the limit, and both are labelled. See the note at
        // the top about why one number would be misleading.
        $total = (clone $query)->count();
        // Single-quoted separator: a double-quoted literal is an identifier under MySQL's
        // ANSI_QUOTES mode, and this has to mean the same thing on every estate.
        $distinct = (clone $query)->distinct()->count(DB::raw("CONCAT_WS('|', c.title, c.date_)"));

        $rows = $query
            ->selectRaw(
                'c.id, c.title, c.message, c.date_, c.file_name, c.syear,
                 c.standard_id, c.division_id, c.type AS type_id,
                 t.type AS circular_type,
                 std.name AS standard_name, div.name AS division_name'
            )
            ->orderByDesc('c.date_')
            ->orderByDesc('c.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'distinct_circulars' => $distinct,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'circulars' => $rows->map(static function ($row) {
                $attachment = trim((string) $row->file_name);

                return [
                    'circular_id' => (int) $row->id,
                    'title' => $row->title,
                    'message' => $row->message,
                    'circular_date' => $row->date_,
                    'type_id' => $row->type_id === null ? null : (int) $row->type_id,
                    'circular_type' => $row->circular_type,
                    'standard_id' => $row->standard_id === null ? null : (int) $row->standard_id,
                    'standard_name' => $row->standard_name,
                    'division_id' => $row->division_id === null ? null : (int) $row->division_id,
                    'division_name' => $row->division_name,
                    'attachment' => $attachment === '' ? null : $attachment,
                    'has_attachment' => $attachment !== '',
                    'academic_year' => $row->syear === null ? null : (int) $row->syear,
                ];
            })->all(),
            'rule' => 'One row is one circular published to one class. `count` is rows; '
                .'`distinct_circulars` groups rows sharing a title and a date. The record holds no '
                .'read receipt, so no readership or delivery figure is reported.',
        ];
    }

    /**
     * The circular types available, with how many circulars each carries in this scope.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function types(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('circular_type')) {
            return ['count' => 0, 'circular_types' => []];
        }

        $rows = DB::table('circular_type')
            ->orderBy('type')
            ->limit(min(max((int) ($filters['limit'] ?? 100), 1), 200))
            ->get(['id', 'type']);

        $used = [];

        if (Schema::hasTable('circular')) {
            $counts = $this->baseQuery($context)
                ->selectRaw('c.type AS type_id, COUNT(*) AS total')
                ->groupBy('c.type')
                ->get();

            foreach ($counts as $row) {
                $used[(int) $row->type_id] = (int) $row->total;
            }
        }

        return [
            'count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'circular_types' => $rows->map(static fn ($row) => [
                'type_id' => (int) $row->id,
                'type' => $row->type,
                // Scoped to this institute and year, so a shared vocabulary entry no school
                // here uses reports zero rather than somebody else's total.
                'circulars_published' => $used[(int) $row->id] ?? 0,
            ])->all(),
            'rule' => 'Circular types are an estate-wide vocabulary. The count beside each is this '
                .'institute and academic year only.',
        ];
    }

    /**
     * The circular join, scoped to the caller's institute and academic year.
     *
     * `standard` and `division` are left joins: a circular addressed to a class that has
     * since been renamed or removed is still a circular the school published, and dropping
     * it would quietly shrink the register.
     */
    private function baseQuery(McpRequestContext $context): Builder
    {
        $query = DB::table('circular as c')
            ->leftJoin('circular_type as t', 't.id', '=', 'c.type')
            ->leftJoin('standard as std', 'std.id', '=', 'c.standard_id')
            ->leftJoin('division as div', function ($join) {
                $join->on('div.id', '=', 'c.division_id')
                    ->on('div.sub_institute_id', '=', 'c.sub_institute_id');
            })
            ->where('c.sub_institute_id', $context->selectedInstituteId);

        if ($context->academicYear !== null) {
            $query->where('c.syear', $context->academicYear);
        }

        return $query;
    }
}
