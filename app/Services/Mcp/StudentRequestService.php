<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student change requests, as `student_change_request` records them.
 *
 * A request is a family or a class teacher asking the school to change something on a
 * child's record - the type is a row in `student_change_req_type`, the reason and the
 * description are free text the requester wrote, and `PROOF_OF_DOCUMENT` is whatever
 * they attached. `STATUS` is the enum the schema declares: Pending, Approved, Rejected,
 * with `DECIDED_BY` and `DECIDED_ON` filled in when somebody decided.
 *
 * THIS IS THE REQUEST, NOT THE STUDENT
 *
 * The join to `tblstudent` exists to name the child a request is about and to place them
 * in a class, because a request with no name on it is unusable. It does not make this a
 * student directory: no academic, attendance, fee or medical field is read, and nothing
 * here can answer a question about how the child is doing. A caller that wants the
 * student record asks the Student module, which has its own tools and its own rights.
 *
 * WHAT COUNTS AS UNDECIDED
 *
 * `STATUS` defaults to 'Pending', but rows written before
 * 2026_08_10_163830_add_status_columns_student_change_request.php ran can hold an empty
 * value. Both are reported as pending and counted as such, because a request nobody has
 * decided is a request nobody has decided however the column got that way. What is never
 * done is guessing the decision from `DECIDED_ON`.
 *
 * SCOPING, AND WHY IT IS THE STUDENT'S INSTITUTE
 *
 * `studentRequestController::index()` and `indexFixed()` - the two queries the shipped
 * Student Request screens run - scope on `tblstudent.sub_institute_id` and `sr.SYEAR`.
 * This service scopes the same way, on the same inner join, so the AI Stack and the module
 * screen answer with the same rows. The request is about a child, and the child's own
 * institute is the authoritative answer to whose request it is.
 *
 * `sr.SUB_INSTITUTE_ID` exists too, and on this estate it does not always agree: there are
 * request rows filed under one institute whose student belongs to another. Requiring both
 * to match would be stricter than the module screen and would show an empty queue beside a
 * screen full of rows, with nothing to explain the difference. Requiring only the request
 * row's own column would be looser than the screen and could return another school's
 * child. So the child decides, and `requests_filed_under_a_different_institute` reports how
 * many of the rows in scope disagree - a count, never an id, because naming the other
 * institute would be the disclosure this is guarding against.
 *
 * Neither the institute nor the year is ever taken from an argument.
 */
class StudentRequestService
{
    /** The statuses the column's own enum declares. */
    private const STATUSES = ['Pending', 'Approved', 'Rejected'];

    /**
     * The requests on file, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('student_change_request')) {
            return [
                'count' => 0,
                'requests' => [],
                'note' => 'Student requests are not recorded in this estate.',
            ];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = $this->baseQuery($context);

        $status = $this->normaliseStatus($filters['status'] ?? null);

        if ($status !== null) {
            $query->where('sr.STATUS', $status);
        } elseif (! empty($filters['only_pending'])) {
            // Empty as well as 'Pending' - see the note at the top about rows that predate
            // the status column.
            $query->where(function ($inner) {
                $inner->where('sr.STATUS', 'Pending')->orWhereNull('sr.STATUS')->orWhere('sr.STATUS', '');
            });
        }

        foreach ([
            'standard_id' => 'sr.STANDARD_ID',
            'division_id' => 'sr.SECTION_ID',
            'student_id' => 'sr.STUDENT_ID',
            'request_type_id' => 'sr.CHANGE_REQUEST_ID',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        if (! empty($filters['search_text'])) {
            $needle = '%'.$filters['search_text'].'%';
            $query->where(function ($inner) use ($needle) {
                $inner->where('srt.REQUEST_TITLE', 'like', $needle)
                    ->orWhere('sr.REASON', 'like', $needle)
                    ->orWhere('s.first_name', 'like', $needle)
                    ->orWhere('s.last_name', 'like', $needle)
                    ->orWhere('s.enrollment_no', 'like', $needle);
            });
        }

        // Counted before the limit, so a page is never read as the whole queue.
        $total = (clone $query)->count();
        $byStatus = $this->statusTotals($context);

        $rows = $query
            ->selectRaw($this->columns())
            ->orderByDesc('sr.CREATED_ON')
            ->orderByDesc('sr.ID')
            ->limit($limit)
            ->get();

        $misfiled = $this->misfiledCount($context);

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'by_status' => $byStatus,
            'requests_filed_under_a_different_institute' => $misfiled,
            'requests' => $rows->map(fn ($row) => $this->map($row))->all(),
            'rule' => 'Requests are scoped to the student\'s own institute, which is what the Student '
                .'Request screens themselves query. A request with no status recorded is reported as '
                .'pending, and the decision is never inferred from a decision date.',
            'data_note' => $misfiled === 0
                ? null
                : $misfiled.' of the requests in scope carry a different institute id on the request row '
                    .'than on the student record. They are included because the student belongs to this '
                    .'institute, which is the scoping the module screens use.',
        ];
    }

    /**
     * One request in full, with everything the record holds about it.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function details(McpRequestContext $context, array $arguments): array
    {
        if (! Schema::hasTable('student_change_request')) {
            return ['found' => false, 'note' => 'Student requests are not recorded in this estate.'];
        }

        $requestId = (int) ($arguments['request_id'] ?? 0);

        if ($requestId < 1) {
            return ['found' => false, 'note' => 'A request id is required.'];
        }

        $row = $this->baseQuery($context)
            ->where('sr.ID', $requestId)
            ->selectRaw($this->columns())
            ->first();

        if ($row === null) {
            // Deliberately the same answer for "no such request" and "a request belonging
            // to another institute": confirming that an id exists elsewhere is itself a
            // disclosure across the tenant boundary.
            return [
                'found' => false,
                'request_id' => $requestId,
                'note' => 'No request with that id exists for this institute and academic year.',
            ];
        }

        $request = $this->map($row);

        return [
            'found' => true,
            'request' => $request,
            'proof_required' => $request['proof_required'],
            'proof_supplied' => $request['proof_of_document'] !== null,
            'rule' => 'Whether proof was required comes from the request type; whether it was supplied '
                .'comes from the request row. Neither is inferred from the other.',
        ];
    }

    /**
     * The request types this institute has defined, so a caller can name one.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function types(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('student_change_req_type')) {
            return ['count' => 0, 'request_types' => []];
        }

        $query = DB::table('student_change_req_type')
            ->where('SUB_INSTITUTE_ID', $context->selectedInstituteId);

        if ($context->academicYear !== null) {
            // The type table carries the year too, and a school redefines its types each
            // year. A type row with no year is shared and stays visible.
            $query->where(function ($inner) use ($context) {
                $inner->where('SYEAR', $context->academicYear)->orWhereNull('SYEAR')->orWhere('SYEAR', '');
            });
        }

        $rows = $query
            ->orderBy('REQUEST_TITLE')
            ->limit(min(max((int) ($filters['limit'] ?? 100), 1), 200))
            ->get(['ID', 'REQUEST_TITLE', 'PROOF_DOCUMENT_REQUIED', 'PROOF_DOCUMENT_NAME', 'AMOUNT', 'SYEAR']);

        return [
            'count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'request_types' => $rows->map(static fn ($row) => [
                'request_type_id' => (int) $row->ID,
                'title' => $row->REQUEST_TITLE,
                'proof_required' => strtoupper(trim((string) $row->PROOF_DOCUMENT_REQUIED)) === 'Y',
                'proof_document_name' => $row->PROOF_DOCUMENT_NAME,
                // A string column in the schema. Reported as recorded rather than cast to a
                // number that would turn a blank into a zero fee.
                'amount' => $row->AMOUNT,
                'academic_year' => $row->SYEAR,
            ])->all(),
        ];
    }

    /**
     * How many requests sit in each status, across the whole queue for this scope.
     *
     * Reported beside a limited page so "12 shown" is never read as "12 pending".
     *
     * @return array<string, int>
     */
    private function statusTotals(McpRequestContext $context): array
    {
        $rows = $this->baseQuery($context)
            ->selectRaw('sr.STATUS AS status, COUNT(*) AS total')
            ->groupBy('sr.STATUS')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $status = trim((string) $row->status);
            $key = $status === '' ? 'Pending' : $status;
            $totals[$key] = ($totals[$key] ?? 0) + (int) $row->total;
        }

        return $totals;
    }

    /** One of the enum's values, or null when the caller named something else. */
    private function normaliseStatus(mixed $value): ?string
    {
        $given = strtolower(trim((string) ($value ?? '')));

        if ($given === '') {
            return null;
        }

        foreach (self::STATUSES as $status) {
            if (strtolower($status) === $given) {
                return $status;
            }
        }

        return null;
    }

    /**
     * The join every read uses, scoped to the caller's institute and academic year.
     *
     * The student join is an inner join and it is the tenant boundary: a request naming a
     * student this institute does not have cannot come back, whatever `sr.SUB_INSTITUTE_ID`
     * says. See the note at the top for why the child's institute decides rather than the
     * request row's own column.
     */
    private function baseQuery(McpRequestContext $context): Builder
    {
        $query = DB::table('student_change_request as sr')
            ->join('tblstudent as s', function ($join) use ($context) {
                $join->on('s.id', '=', 'sr.STUDENT_ID')
                    ->where('s.sub_institute_id', '=', $context->selectedInstituteId);
            })
            ->leftJoin('student_change_req_type as srt', 'srt.ID', '=', 'sr.CHANGE_REQUEST_ID')
            ->leftJoin('standard as std', 'std.id', '=', 'sr.STANDARD_ID')
            ->leftJoin('division as div', 'div.id', '=', 'sr.SECTION_ID')
            ->leftJoin('tbluser as decider', function ($join) use ($context) {
                $join->on('decider.id', '=', 'sr.DECIDED_BY')
                    ->where('decider.sub_institute_id', '=', $context->selectedInstituteId);
            });

        if ($context->academicYear !== null) {
            $query->where('sr.SYEAR', $context->academicYear);
        }

        return $query;
    }

    /**
     * How many in-scope requests are filed under an institute other than the child's.
     *
     * A count, never an id. It exists so an operator looking at a queue that disagrees with
     * a colleague's can see that the disagreement is in the data rather than in the screen.
     */
    private function misfiledCount(McpRequestContext $context): int
    {
        return (int) $this->baseQuery($context)
            ->where(function ($inner) use ($context) {
                $inner->where('sr.SUB_INSTITUTE_ID', '!=', $context->selectedInstituteId)
                    ->orWhereNull('sr.SUB_INSTITUTE_ID');
            })
            ->count();
    }

    /** The columns every read selects, so the list and the detail cannot disagree. */
    private function columns(): string
    {
        return "sr.ID, sr.STATUS, sr.REASON, sr.DESCRIPTION, sr.PROOF_OF_DOCUMENT,
                sr.CREATED_ON, sr.DECIDED_BY, sr.DECIDED_ON, sr.SYEAR,
                sr.STUDENT_ID, sr.STANDARD_ID, sr.SECTION_ID, sr.CHANGE_REQUEST_ID,
                srt.REQUEST_TITLE, srt.PROOF_DOCUMENT_REQUIED, srt.PROOF_DOCUMENT_NAME,
                std.name AS standard_name, div.name AS division_name,
                s.enrollment_no, s.mobile,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                CONCAT_WS(' ', decider.first_name, decider.last_name) AS decided_by_name";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $status = trim((string) $row->STATUS);
        $proof = trim((string) $row->PROOF_OF_DOCUMENT);

        return [
            'request_id' => (int) $row->ID,
            // Empty reads as Pending. See the note at the top.
            'status' => $status === '' ? 'Pending' : $status,
            'request_type_id' => $row->CHANGE_REQUEST_ID === null ? null : (int) $row->CHANGE_REQUEST_ID,
            'request_title' => $row->REQUEST_TITLE,
            'reason' => $row->REASON,
            'description' => $row->DESCRIPTION,
            'proof_required' => strtoupper(trim((string) $row->PROOF_DOCUMENT_REQUIED)) === 'Y',
            'proof_document_name' => $row->PROOF_DOCUMENT_NAME,
            'proof_of_document' => $proof === '' ? null : $proof,
            'student_id' => (int) $row->STUDENT_ID,
            'student_name' => trim((string) $row->student_name) ?: null,
            'enrollment_no' => $row->enrollment_no,
            'mobile' => $row->mobile,
            'standard_id' => $row->STANDARD_ID === null ? null : (int) $row->STANDARD_ID,
            'standard_name' => $row->standard_name,
            'division_id' => $row->SECTION_ID === null ? null : (int) $row->SECTION_ID,
            'division_name' => $row->division_name,
            'academic_year' => $row->SYEAR,
            'raised_on' => $row->CREATED_ON,
            'decided_on' => $row->DECIDED_ON,
            'decided_by' => $row->DECIDED_BY === null ? null : (int) $row->DECIDED_BY,
            'decided_by_name' => trim((string) $row->decided_by_name) ?: null,
        ];
    }
}
