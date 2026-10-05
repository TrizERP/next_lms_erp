<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Complaints, as the `complaint` table records them.
 *
 * THE COLUMN CALLED `COMPLAINT_SOLUTION` IS A STATUS, NOT A SOLUTION
 *
 * This is the single most important thing about this module, and it is not guessable from
 * the name. Across the whole estate the column holds exactly two values:
 *
 *     PENDING   30 rows
 *     COMPLETE   5 rows
 *
 * and those are the two rows of `complaint_status` where `TYPE = 'COMPLAIN'`. It is the
 * status field. There is NO free-text resolution anywhere on this table.
 *
 * So "how was this complaint resolved" cannot be answered, and a summary that read the
 * column as a solution would report every open complaint as having been solved with the
 * word "PENDING". Nothing here returns a `solution`; it returns `status`, and the rule on
 * every payload says the resolution text does not exist.
 *
 * WHAT ELSE IS NOT RECORDED
 *
 * No priority. No category. No severity. No department beyond a user-group id, and no
 * escalation of any kind — no escalated-to, no escalated-on, no SLA, no due date. The
 * brief's "which complaints need escalation" and "show high-priority complaints" have no
 * column behind them, and every published prompt forbids inventing one. Age in days is
 * arithmetic on the complaint date and is not a breach of anything, because nothing here
 * records what was promised.
 *
 * A COMPLAINT NAMES THE PERSON WHO MADE IT
 *
 * `COMPLAINT_BY` is a real user. The screen inner-joins that user, so a complaint whose
 * author is no longer an active user of the institute disappears from it. This reads with
 * a LEFT join instead — a complaint that exists should be counted — and reports how many
 * have an unresolvable author rather than quietly dropping them. Reporting more completely
 * than the screen is safe; hiding rows is not.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, and `syear` where the caller
 * carries an academic year. Both user lookups are joined on institute.
 */
class ComplaintService
{
    /** The status spellings that mean the complaint is closed. Compared upper-cased. */
    private const CLOSED = ['COMPLETE', 'COMPLETED', 'CLOSED', 'RESOLVED'];

    /**
     * Complaints, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('complaint')) {
            return ['count' => 0, 'complaints' => [], 'note' => 'Complaints are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $this->applyFilters($query, $filters);

        $state = trim((string) ($filters['state'] ?? 'any'));

        if ($state === 'closed') {
            $query->whereIn(DB::raw('UPPER(TRIM(c.COMPLAINT_SOLUTION))'), self::CLOSED);
        } elseif ($state === 'open') {
            $query->whereNotIn(DB::raw('UPPER(TRIM(c.COMPLAINT_SOLUTION))'), self::CLOSED);
        }

        // Counted over the whole filtered set, like `count`.
        $total = (clone $query)->count();
        $closed = (clone $query)->whereIn(DB::raw('UPPER(TRIM(c.COMPLAINT_SOLUTION))'), self::CLOSED)->count();
        $unknownAuthor = (clone $query)->whereNull('u.id')->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderByDesc('c.DATE')
            ->orderByDesc('c.ID')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'closed' => $closed,
            'open' => $total - $closed,
            // Surfaced rather than hidden. The complaint screen's inner join drops these.
            'raised_by_someone_not_an_active_user_here' => $unknownAuthor,
            'figures_cover' => 'every complaint matching these filters, not only the rows listed',
            'complaints' => $rows->map(fn ($row) => $this->map($row))->all(),
            'rule' => $this->rule(),
        ];
    }

    /**
     * Complaints counted by status and by the group they were assigned to.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('complaint')) {
            return ['count' => 0, 'by_status' => [], 'note' => 'Complaints are not recorded in this estate.'];
        }

        $query = $this->query($context);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $closed = (clone $query)->whereIn(DB::raw('UPPER(TRIM(c.COMPLAINT_SOLUTION))'), self::CLOSED)->count();

        $byStatus = (clone $query)
            ->selectRaw("CASE WHEN c.COMPLAINT_SOLUTION IS NULL OR TRIM(c.COMPLAINT_SOLUTION) = '' "
                ."THEN '(no status recorded)' ELSE c.COMPLAINT_SOLUTION END AS status, COUNT(*) AS complaints")
            ->groupByRaw("CASE WHEN c.COMPLAINT_SOLUTION IS NULL OR TRIM(c.COMPLAINT_SOLUTION) = '' "
                ."THEN '(no status recorded)' ELSE c.COMPLAINT_SOLUTION END")
            ->orderByDesc('complaints')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'means_closed' => in_array(strtoupper(trim((string) $row->status)), self::CLOSED, true),
                'complaints' => (int) $row->complaints,
            ])
            ->all();

        $byGroup = (clone $query)
            ->selectRaw('c.COMPLAINT_SOLUTION_USER_GROUP_ID AS group_id, COUNT(*) AS complaints')
            ->groupBy('c.COMPLAINT_SOLUTION_USER_GROUP_ID')
            ->orderByDesc('complaints')
            ->limit(20)
            ->get()
            ->map(static fn ($row) => [
                // An id only. This table records no department name, and nothing here
                // invents one.
                'user_group_id' => $row->group_id === null || $row->group_id === '' ? null : (int) $row->group_id,
                'complaints' => (int) $row->complaints,
            ])
            ->all();

        return [
            'count' => $total,
            'academic_year' => $context->academicYear,
            'closed' => $closed,
            'open' => $total - $closed,
            'by_status' => $byStatus,
            'by_assigned_group' => $byGroup,
            'rule' => $this->rule(),
        ];
    }

    /** The rule every complaint answer carries. */
    private function rule(): string
    {
        return 'One row is one complaint. THE COLUMN NAMED `COMPLAINT_SOLUTION` IS THE STATUS FIELD, not a '
            .'resolution: across this estate it holds only the words PENDING and COMPLETE, which are the '
            .'two `complaint_status` rows for complaints. There is NO resolution text anywhere on this '
            .'table, so never state how a complaint was resolved, what was done about it, or what was '
            .'said to the person. This table also records NO priority, NO category, NO severity, NO due '
            .'date, NO SLA and NO escalation of any kind — never describe a complaint as high priority, '
            .'urgent, breaching or needing escalation, and never rank them. `days_since_raised` is '
            .'arithmetic on the date and is not evidence that anything was promised. A complaint names '
            .'the person who made it; do not repeat their name into a summary that others will read.';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['user_group_id'])) {
            $query->where('c.COMPLAINT_SOLUTION_USER_GROUP_ID', (int) $filters['user_group_id']);
        }

        if (! empty($filters['raised_by'])) {
            $query->where('c.COMPLAINT_BY', (int) $filters['raised_by']);
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('c.DATE', $operator, $date);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('c.TITLE', 'like', $needle)->orWhere('c.DESCRIPTION', 'like', $needle);
            });
        }
    }

    /** The complaint join, scoped at every hop that carries an institute. */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        // LEFT, not the screen's inner join: a complaint that exists should be counted
        // even when the person who raised it is no longer an active user here.
        $query = DB::table('complaint as c')
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'c.COMPLAINT_BY')->where('u.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as s', function ($join) use ($institute) {
                $join->on('s.id', '=', 'c.COMPLAINT_SOLUTION_BY')->where('s.sub_institute_id', '=', $institute);
            })
            ->where('c.SUB_INSTITUTE_ID', $institute);

        if ($context->academicYear !== null) {
            $query->where('c.SYEAR', $context->academicYear);
        }

        return $query;
    }

    private function columns(): string
    {
        return "c.ID AS complaint_id, c.DATE, c.TITLE, c.DESCRIPTION, c.COMPLAINT_BY,
                c.COMPLAINT_SOLUTION, c.COMPLAINT_SOLUTION_BY, c.COMPLAINT_SOLUTION_USER_GROUP_ID,
                c.ATTACHEMENT, c.CREATED_DATE, c.UPDATED_ON,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS raised_by_name,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS closed_by_name,
                DATEDIFF(CURDATE(), c.DATE) AS days_since_raised";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $status = trim((string) ($row->COMPLAINT_SOLUTION ?? ''));
        $closed = in_array(strtoupper($status), self::CLOSED, true);

        return [
            'complaint_id' => (int) $row->complaint_id,
            'title' => $row->TITLE,
            'description' => $row->DESCRIPTION,
            'complaint_date' => $row->DATE,
            // Arithmetic on the date. Not a breach — nothing records what was promised.
            'days_since_raised' => $row->days_since_raised === null ? null : (int) $row->days_since_raised,
            // Read from COMPLAINT_SOLUTION, which is the status field. See the class note.
            'status' => $status !== '' ? $status : null,
            'status_normalised' => $status === '' ? 'no status recorded' : ($closed ? 'closed' : 'open'),
            'raised_by_user_id' => $row->COMPLAINT_BY === null ? null : (int) $row->COMPLAINT_BY,
            // Null when the author is not an active user of this institute — a record to
            // correct, not a name to borrow from elsewhere.
            'raised_by' => trim((string) ($row->raised_by_name ?? '')) ?: null,
            'closed_by' => trim((string) ($row->closed_by_name ?? '')) ?: null,
            'assigned_user_group_id' => $row->COMPLAINT_SOLUTION_USER_GROUP_ID === null
                || $row->COMPLAINT_SOLUTION_USER_GROUP_ID === ''
                ? null
                : (int) $row->COMPLAINT_SOLUTION_USER_GROUP_ID,
            // Whether a file is attached, never the file.
            'attachment_present' => trim((string) ($row->ATTACHEMENT ?? '')) !== '',
            'last_updated_on' => $row->UPDATED_ON ?: null,
        ];
    }
}
