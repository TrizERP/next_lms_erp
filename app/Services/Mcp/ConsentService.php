<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consents, as the `consent_master` table records them.
 *
 * ONE ROW IS ONE CONSENT ASKED OF ONE STUDENT'S FAMILY
 *
 * `consent_master` carries the student, the class, a title (what is being consented to),
 * a date, whether the consent is accountable, an amount, an imprest head, a status and who
 * raised it.
 *
 * AN EMPTY STATUS IS "NO DECISION RECORDED". IT IS NOT A REFUSAL.
 *
 * This is the same three-state rule the PTM module carries, and it matters more here,
 * because the thing being recorded is a parent's permission for their child.
 * `report_consent_masterController` renders the column as
 *
 *     if(status = NULL, 'Pending', status)
 *
 * which shows what the office means by an empty status — nobody has answered yet — though
 * the SQL as written never fires, because `= NULL` is never true in SQL and the intended
 * test is `IS NULL`. The intent is what this class implements, correctly.
 *
 * So there are three states and never two:
 *
 *   · a decision is recorded — the value the office entered, passed through verbatim
 *   · no decision is recorded — awaiting an answer
 *   · the consent does not exist for that student at all — absent from the register
 *
 * Nothing here collapses the second into "refused", "declined" or "denied". A family that
 * has not answered has not said no, and a summary that reported them as a refusal would
 * put a decision in a parent's mouth.
 *
 * THE RECORDED VOCABULARY IS NOT GUESSED AT
 *
 * Whatever non-empty value `status` holds is returned exactly as stored, and this class
 * does not map it onto a granted/declined vocabulary of its own. The office's words are
 * the office's; inventing a two-value scheme and sorting real entries into it would be a
 * claim about a decision this code has no basis for.
 *
 * THERE IS NO EXPIRY, AND NO REMINDER HISTORY
 *
 * "Which consents are expiring soon?" has no column behind it: `consent_master` records a
 * date the consent was raised and nothing that expires. It also records no reminder sent,
 * no chase and no response time. Nothing here may report any of them.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, and `syear` where the caller
 * carries an academic year. The student, class and raiser lookups are joined on institute
 * as well, so a name from another school can never appear against this school's consent.
 */
class ConsentService
{
    /**
     * Consents, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function records(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('consent_master')) {
            return ['count' => 0, 'consents' => [], 'note' => 'Consents are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $this->applyFilters($query, $filters);

        // Counted before the limit, so a page is never read as the whole register — and
        // the two state figures over the SAME whole set, because "how many are still
        // waiting" is the question this tab exists to answer and a page-scoped answer to
        // it is worse than none.
        $total = (clone $query)->count();
        $awaiting = (clone $query)->where($this->awaitingDecision())->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderByDesc('c.date')
            ->orderByDesc('c.ID')
            ->limit($limit)
            ->get();

        $consents = $rows->map(fn ($row) => $this->map($row))->all();

        return [
            'count' => $total,
            'row_count' => count($consents),
            'academic_year' => $context->academicYear,
            'awaiting_decision' => $awaiting,
            'decision_recorded' => $total - $awaiting,
            'figures_cover' => 'every consent matching these filters, not only the rows listed',
            'consents' => $consents,
            'rule' => $this->rule(),
        ];
    }

    /**
     * Consents counted by decision state and by accountability.
     *
     * Both groupings run over the whole filtered set rather than a page, and the three
     * states are kept apart in the totals exactly as they are in the rows.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('consent_master')) {
            return ['count' => 0, 'by_decision' => [], 'note' => 'Consents are not recorded in this estate.'];
        }

        $query = $this->query($context);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();

        $awaiting = (clone $query)->where($this->awaitingDecision())->count();

        // Grouped on the stored value, never on a vocabulary invented here. An empty value
        // is reported under its own name rather than folded into one of the others.
        $byDecision = (clone $query)
            ->selectRaw("CASE WHEN c.status IS NULL OR TRIM(c.status) = '' THEN '(no decision recorded)' "
                ."ELSE c.status END AS decision, COUNT(*) AS consents")
            ->groupByRaw("CASE WHEN c.status IS NULL OR TRIM(c.status) = '' THEN '(no decision recorded)' "
                .'ELSE c.status END')
            ->orderByDesc('consents')
            ->limit(20)
            ->get()
            ->map(static fn ($row) => ['decision' => $row->decision, 'consents' => (int) $row->consents])
            ->all();

        $byAccountability = (clone $query)
            ->selectRaw('c.accountable_status, COUNT(*) AS consents, SUM(c.amount) AS amount')
            ->groupBy('c.accountable_status')
            ->orderByDesc('consents')
            ->get()
            ->map(static fn ($row) => [
                'accountable_status' => $row->accountable_status ?: null,
                'consents' => (int) $row->consents,
                'amount' => $row->amount === null ? null : round((float) $row->amount, 2),
            ])
            ->all();

        return [
            'count' => $total,
            'academic_year' => $context->academicYear,
            'awaiting_decision' => $awaiting,
            'decision_recorded' => $total - $awaiting,
            'by_decision' => $byDecision,
            'by_accountability' => $byAccountability,
            'rule' => $this->rule(),
        ];
    }

    /**
     * The three-state rule, written once and attached to both payloads.
     *
     * Attached to the totals as well as the rows, because a count is exactly where two of
     * the three states get quietly merged.
     */
    private function rule(): string
    {
        return 'A consent has THREE states and never two: a decision is recorded, no decision is recorded '
            .'yet, or the consent does not exist for that student at all. An empty status means nobody has '
            .'answered — it must NEVER be reported as a refusal, a decline or a denial, and a family that '
            .'has not answered has not said no. Any recorded decision is passed through exactly as the '
            .'office entered it and is not mapped onto a granted/declined vocabulary here. This table '
            .'records NO expiry date, NO reminder sent and NO response time, so nothing may be described '
            .'as expiring, chased or overdue.';
    }

    /** The "nobody has answered yet" test, written once so the rows and the totals agree. */
    private function awaitingDecision(): callable
    {
        return static function ($inner): void {
            $inner->whereNull('c.status')->orWhereRaw("TRIM(c.status) = ''");
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach ([
            'student_id' => 'c.student_id',
            'standard_id' => 'c.standard_id',
            'division_id' => 'c.division_id',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $decision = trim((string) ($filters['decision'] ?? 'any'));

        if ($decision === 'awaiting') {
            $query->where($this->awaitingDecision());
        } elseif ($decision === 'recorded') {
            $query->whereNotNull('c.status')->whereRaw("TRIM(c.status) <> ''");
        }

        $accountable = trim((string) ($filters['accountable_status'] ?? ''));

        if ($accountable !== '') {
            $query->where('c.accountable_status', $accountable);
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('c.date', $operator, $date);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('c.title', 'like', $needle)
                    ->orWhere('s.first_name', 'like', $needle)
                    ->orWhere('s.last_name', 'like', $needle)
                    ->orWhere('s.enrollment_no', 'like', $needle);
            });
        }
    }

    /** The consent join, scoped at every hop that carries an institute. */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('consent_master as c')
            ->leftJoin('tblstudent as s', function ($join) use ($institute) {
                $join->on('s.id', '=', 'c.student_id')->where('s.sub_institute_id', '=', $institute);
            })
            ->leftJoin('standard as std', function ($join) use ($institute) {
                $join->on('std.id', '=', 'c.standard_id')->where('std.sub_institute_id', '=', $institute);
            })
            ->leftJoin('division as div', function ($join) use ($institute) {
                $join->on('div.id', '=', 'c.division_id')->where('div.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'c.created_by')->where('u.sub_institute_id', '=', $institute);
            })
            ->where('c.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            // `syear` is stored as a string on this table; MySQL coerces the comparison,
            // and casting here would defeat the index.
            $query->where('c.syear', $context->academicYear);
        }

        return $query;
    }

    private function columns(): string
    {
        return "c.ID AS consent_id, c.student_id, c.title, c.date, c.accountable_status, c.amount,
                c.imprest_head_id, c.status, c.created_on, c.created_by,
                c.standard_id, c.division_id,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                s.enrollment_no,
                std.name AS standard_name, div.name AS division_name,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS raised_by_name";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $status = trim((string) ($row->status ?? ''));
        $recorded = $status !== '';

        return [
            'consent_id' => (int) $row->consent_id,
            'title' => $row->title,
            'consent_date' => $row->date,
            'student_id' => $row->student_id === null ? null : (int) $row->student_id,
            // Null when the student is not of this institute — a record to correct, not a
            // name to borrow from elsewhere.
            'student_name' => trim((string) ($row->student_name ?? '')) ?: null,
            'enrollment_no' => $row->enrollment_no ?: null,
            'standard_name' => $row->standard_name,
            'division_name' => $row->division_name,
            'accountable_status' => $row->accountable_status ?: null,
            'amount' => $row->amount === null || $row->amount === '' ? null : round((float) $row->amount, 2),
            'imprest_head_id' => $row->imprest_head_id === null ? null : (int) $row->imprest_head_id,
            // The office's own word, verbatim. Never mapped onto a vocabulary of ours.
            'decision' => $recorded ? $status : null,
            'decision_recorded' => $recorded,
            // Spelled out so a model reading the row cannot reach for "declined".
            'decision_state' => $recorded ? 'recorded' : 'no decision recorded yet',
            'raised_on' => $row->created_on,
            'raised_by' => trim((string) ($row->raised_by_name ?? '')) ?: null,
        ];
    }
}
