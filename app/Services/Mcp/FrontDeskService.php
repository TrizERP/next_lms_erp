<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The front desk register, as the `front_desk` table records it.
 *
 * ONE ROW IS ONE PERSON COMING IN TO MEET SOMEBODY ABOUT A STUDENT
 *
 * `front_desk` carries the visitor type, the date, an entry and exit time, a title and
 * description, the student the visit concerns, and the member of staff being met.
 *
 * IT IS NOT THE SCHOOL'S VISITOR LOG, AND THIS MODULE MUST NOT PRETEND IT IS
 *
 * The estate holds ONE row in `front_desk` in total. The register the schools actually
 * use is `visitor_master`, which holds 869 and belongs to the Visitor Management module
 * registered separately. Neither module binds the other's tools.
 *
 * That matters for what this module may say. "How many visitors did we have today" asked
 * here can only be answered about this register, and an empty answer means this register
 * is empty — not that nobody came. Every payload says so, because a front-desk answer of
 * "no visitors today" is the kind that gets repeated.
 *
 * THE NON-ADMIN RESTRICTION IS CARRIED OVER, NOT DROPPED
 *
 * `frontdeskController::index()` shows a non-admin only the rows where they are the person
 * being met:
 *
 *     if (strtoupper($user_profile_name) != 'ADMIN') { $q->where('fd.TO_WHOM_MEET', $user_id); }
 *
 * The same rule is applied here, from the caller's own token. A teacher asking the
 * assistant what is at the front desk sees what the screen would show them and not one row
 * more — an AI layer that quietly returned the whole register would be a way around a
 * restriction the application already makes.
 *
 * AN EXIT TIME THAT IS ABSENT IS AN EXIT THAT WAS NOT RECORDED
 *
 * The same three states the visitor register has: an exit was recorded, an entry was and no
 * exit was, or no entry was recorded at all. Nothing here says a named person is in the
 * building.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, plus the caller's own user id
 * for the non-admin rule. The student and staff lookups are joined on institute too.
 */
class FrontDeskService
{
    /**
     * Front desk visits, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function visits(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('front_desk')) {
            return ['count' => 0, 'visits' => [], 'note' => 'A front desk register is not kept in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        foreach (['student_id' => 'fd.STUDENT_ID', 'staff_id' => 'fd.TO_WHOM_MEET'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $visitorType = trim((string) ($filters['visitor_type'] ?? ''));

        if ($visitorType !== '') {
            $query->where('fd.VISITOR_TYPE', 'like', '%'.$visitorType.'%');
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('fd.DATE', $operator, $date);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('fd.TITLE', 'like', $needle)->orWhere('fd.DESCRIPTION', 'like', $needle);
            });
        }

        // Counted over the whole filtered set, and the three presence states with it.
        $total = (clone $query)->count();
        $noEntry = (clone $query)
            ->where(static function ($inner): void {
                $inner->whereNull('fd.IN_TIME')->orWhereRaw("TRIM(fd.IN_TIME) = ''");
            })
            ->count();
        $noExit = (clone $query)
            ->whereNotNull('fd.IN_TIME')
            ->whereRaw("TRIM(fd.IN_TIME) <> ''")
            ->where(static function ($inner): void {
                $inner->whereNull('fd.OUT_TIME')->orWhereRaw("TRIM(fd.OUT_TIME) = ''");
            })
            ->count();

        $rows = $query
            ->selectRaw("fd.ID, fd.VISITOR_TYPE, fd.DATE, fd.IN_TIME, fd.OUT_TIME, fd.OUT_DATE,
                fd.TITLE, fd.DESCRIPTION, fd.STUDENT_ID, fd.TO_WHOM_MEET, fd.VISITOR_PHOTO, fd.CREATED_ON,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                s.enrollment_no,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS staff_name")
            ->orderByDesc('fd.DATE')
            ->orderByDesc('fd.ID')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'exit_recorded' => $total - $noEntry - $noExit,
            'no_exit_recorded' => $noExit,
            'no_entry_recorded' => $noEntry,
            // Stated on every payload so a restricted view is never read as the whole desk.
            'scope' => $context->isAdmin
                ? 'every front desk row for this institute'
                : 'only the rows where the caller is the person being met, which is the rule the front desk screen applies to a non-admin',
            'figures_cover' => 'every visit matching these filters, not only the rows listed',
            'visits' => $rows->map(static function ($row) {
                $in = trim((string) ($row->IN_TIME ?? ''));
                $out = trim((string) ($row->OUT_TIME ?? ''));
                $entryRecorded = $in !== '';

                return [
                    'visit_id' => (int) $row->ID,
                    'visitor_type' => $row->VISITOR_TYPE ?: null,
                    'title' => $row->TITLE ?: null,
                    'description' => $row->DESCRIPTION ?: null,
                    'date' => $row->DATE,
                    'in_time' => $entryRecorded ? $in : null,
                    'out_time' => $out !== '' ? $out : null,
                    'out_date' => $row->OUT_DATE ?: null,
                    'entry_recorded' => $entryRecorded,
                    // Null where there is no entry to exit from, rather than false, which
                    // would read as "did not leave".
                    'exit_recorded' => $entryRecorded ? ($out !== '') : null,
                    'presence_state' => $entryRecorded
                        ? ($out !== '' ? 'exit recorded' : 'no exit recorded — may have left without signing out')
                        : 'no entry recorded',
                    'student_id' => $row->STUDENT_ID === null ? null : (int) $row->STUDENT_ID,
                    // Null when the student is not of this institute — a record to correct,
                    // not a name to borrow from elsewhere.
                    'student_name' => trim((string) ($row->student_name ?? '')) ?: null,
                    'enrollment_no' => $row->enrollment_no ?: null,
                    'staff_id' => $row->TO_WHOM_MEET === null ? null : (int) $row->TO_WHOM_MEET,
                    'to_meet' => trim((string) ($row->staff_name ?? '')) ?: null,
                    // Whether a photo was captured, never the file.
                    'photo_captured' => trim((string) ($row->VISITOR_PHOTO ?? '')) !== '',
                    'recorded_on' => $row->CREATED_ON,
                ];
            })->all(),
            'rule' => 'One row is one front desk visit about one student. A missing exit time means no exit '
                .'was RECORDED — the visitor may still be on site or may have left without signing out — '
                .'so never state that a named person is currently in the building. THIS REGISTER IS NOT '
                .'THE SCHOOL\'S WHOLE VISITOR LOG: the Visitor Management module keeps a separate and much '
                .'larger one that is not included here, so an empty result means this register is empty '
                .'and NEVER that nobody visited the school.',
        ];
    }

    /**
     * The front desk join, scoped by institute and by the caller's own rights.
     */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('front_desk as fd')
            ->leftJoin('tblstudent as s', function ($join) use ($institute) {
                $join->on('s.id', '=', 'fd.STUDENT_ID')->where('s.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'fd.TO_WHOM_MEET')->where('u.sub_institute_id', '=', $institute);
            })
            ->where('fd.SUB_INSTITUTE_ID', $institute);

        if ($context->academicYear !== null) {
            $query->where('fd.SYEAR', $context->academicYear);
        }

        // The rule `frontdeskController::index()` applies. Carried over rather than
        // dropped: the assistant must not be a way around a restriction the screen makes.
        if (! $context->isAdmin) {
            $query->where('fd.TO_WHOM_MEET', $context->userId);
        }

        return $query;
    }
}
