<?php

namespace App\Services\Mcp;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fees COLLECTED, for the dates asked for and no others.
 *
 * WHICH DATE
 *
 * `fees_collect.receiptdate` — the day the money was received, as the school's own receipt records
 * it. Not `created_date` (when the row was keyed in) and not the academic year.
 *
 * WHY THERE IS NO ACADEMIC-YEAR FILTER
 *
 * A receipt is tagged with the academic year it was raised against, which is not the year its money
 * arrived in. Filtering by the chat's current year hid real collections: yesterday's two receipts are
 * tagged 2021 while the school is working in 2022, so "fees collected yesterday" answered "none" for a
 * day that had collections. The app's own Daily Summary report makes the same decision and says why:
 * "money received today belongs to today's report irrespective of the academic year the receipt was
 * raised against". This follows it.
 *
 * WHAT COUNTS
 *
 * Regular receipts (`fees_collect`) and other-fee receipts (`fees_paid_other`), not deleted — the same
 * two sources, and the same "not deleted" rule, as that report. Cancelled receipts are not collected
 * money and are left out.
 *
 * WHAT THE DATES MEAN
 *
 * `period` ("yesterday", "last 7 days", "13 October 2026"), `date`, or `from_date`/`to_date`, resolved
 * by {@see DateRangeResolver} against the server clock. With none of them it is today. A date that
 * cannot be read is reported as unreadable — it is never swapped for today or for all time, so a question
 * about one day cannot return the totals of another.
 */
class FeesCollectionReportService
{
    public function report(McpRequestContext $context, array $filters): array
    {
        $now = Carbon::now();
        $resolved = DateRangeResolver::fromArguments($filters, $now);

        if ($resolved === false) {
            return [
                'headline' => 'I could not read that date, so no collection figures are shown. Try "yesterday", '
                    .'"today", "last 7 days", or a date such as 13 October 2026.',
                'period' => null,
                'count' => 0,
                'summary' => $this->emptySummary(),
                'receipts' => [],
                'note' => 'The requested date could not be understood; nothing was substituted for it.',
            ];
        }

        $defaulted = $resolved === null;
        $resolved ??= DateRangeResolver::fromText('today', $now);

        ['from' => $fromDate, 'to' => $toDate] = $resolved;
        $limit = min(max((int) ($filters['limit'] ?? 25), 1), 100);

        // One student, when the question is about one: by id, or by the name or enrolment number the
        // person used. A name that matches nobody, or several people, is answered as exactly that —
        // never by quietly picking one, and never by falling back to the whole institute's figures.
        $student = null;
        $named = is_string($filters['student'] ?? null) ? trim($filters['student']) : '';

        if (! empty($filters['student_id']) || $named !== '') {
            $found = $this->findStudent($context, (int) ($filters['student_id'] ?? 0), $named);

            if (isset($found['problem'])) {
                return $found['problem'] + [
                    'period' => ['from' => $fromDate, 'to' => $toDate, 'label' => $resolved['label'], 'defaulted_to_today' => $defaulted, 'date_field' => 'receiptdate'],
                    'count' => 0,
                    'summary' => $this->emptySummary(),
                    'receipts' => [],
                ];
            }

            $student = $found['student'];
            $filters['student_id'] = $student['student_id'];
        }

        $regular = $this->regular($context, $filters, $fromDate, $toDate);
        $other = $this->otherFees($context, $filters, $fromDate, $toDate);

        // A receipt is stored one row per term it covers: receipt #31 is fourteen rows and one receipt.
        // "Receipts" counts the distinct receipt numbers; the rows are reported as `fee_lines`.
        $summary = (clone $regular)
            ->selectRaw("COUNT(*) as fee_lines, COUNT(DISTINCT CONCAT(fc.student_id, '-', fc.receipt_no)) as total_receipts, COALESCE(SUM(fc.amount), 0) as total_amount, COALESCE(SUM(fc.fine), 0) as total_fine, COALESCE(SUM(fc.fees_discount), 0) as total_discount")
            ->first();

        $otherSummary = (clone $other)
            ->selectRaw("COUNT(*) as fee_lines, COUNT(DISTINCT CONCAT(fo.student_id, '-', fo.reciept_id)) as receipts, COALESCE(SUM(fo.actual_amountpaid), 0) as amount")
            ->first();

        $modes = $this->byMode((clone $regular), (clone $other));

        $receipts = $regular
            ->selectRaw("fc.id, fc.receipt_no, fc.student_id, fc.receiptdate, fc.payment_mode, fc.amount, fc.fine,
                fc.fees_discount, fc.term_id, fc.created_date,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name, s.enrollment_no")
            ->orderByDesc('fc.receiptdate')
            ->orderByDesc('fc.id')
            ->limit($limit)
            ->get()
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                'receipt_no' => $row->receipt_no,
                'student_id' => (int) $row->student_id,
                'student_name' => trim((string) $row->student_name),
                'enrollment_no' => $row->enrollment_no,
                'receipt_date' => $row->receiptdate,
                'payment_mode' => $row->payment_mode,
                'amount' => (float) $row->amount,
                'fine' => (float) $row->fine,
                'discount' => (float) $row->fees_discount,
                'term_id' => $row->term_id,
                'created_date' => $row->created_date,
            ])
            ->all();

        $otherReceipts = $other
            ->selectRaw("fo.id, fo.reciept_id AS receipt_no, fo.student_id, fo.receiptdate, fo.payment_mode, fo.actual_amountpaid,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name")
            ->orderByDesc('fo.receiptdate')
            ->orderByDesc('fo.id')
            ->limit($limit)
            ->get()
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                'receipt_no' => $row->receipt_no,
                'student_id' => (int) $row->student_id,
                'student_name' => trim((string) $row->student_name),
                'receipt_date' => $row->receiptdate,
                'payment_mode' => $row->payment_mode,
                'amount' => (float) $row->actual_amountpaid,
            ])
            ->all();

        $regularCount = (int) ($summary->total_receipts ?? 0);
        $regularAmount = (float) ($summary->total_amount ?? 0);
        $otherCount = (int) ($otherSummary->receipts ?? 0);
        $otherAmount = (float) ($otherSummary->amount ?? 0);
        $totalCount = $regularCount + $otherCount;
        $totalAmount = $regularAmount + $otherAmount;

        $latest = $totalCount === 0 ? $this->latestBefore($context, $toDate, $student['student_id'] ?? null) : null;
        $lines = (int) ($summary->fee_lines ?? 0) + (int) ($otherSummary->fee_lines ?? 0);

        return [
            'headline' => $this->headline($resolved, $totalCount, $totalAmount, $otherCount, $regularCount, $latest, $filters, $student, $lines),
            'student' => $student,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'label' => $resolved['label'],
                'defaulted_to_today' => $defaulted,
                'date_field' => 'receiptdate',
            ],
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'student_id' => $filters['student_id'] ?? null,
                'payment_mode' => $filters['payment_mode'] ?? null,
                // Reported, so a reader can see it was not applied: see the class note.
                'academic_year' => null,
            ],
            // The list below is the regular receipts, one row per term line; `count` is that list's real
            // total, which can exceed what is listed when a limit applied.
            'count' => (int) ($summary->fee_lines ?? 0),
            'summary' => [
                'total_receipts' => $regularCount,
                'fee_lines' => (int) ($summary->fee_lines ?? 0),
                'total_amount' => $regularAmount,
                'total_fine' => (float) ($summary->total_fine ?? 0),
                'total_discount' => (float) ($summary->total_discount ?? 0),
                'other_fee_receipts' => $otherCount,
                'other_fee_amount' => $otherAmount,
                'all_receipts' => $totalCount,
                'total_collected' => $totalAmount,
            ],
            'by_payment_mode' => $modes,
            'latest_collection_date' => $latest,
            'receipts' => $receipts,
            'other_fee_receipts' => $otherReceipts,
            'rule' => 'Only receipts whose receipt date falls in the period above are counted, whatever academic year '
                .'they were raised against. Cancelled receipts are not counted.',
        ];
    }

    // ---------------------------------------------------------------- queries

    private function regular(McpRequestContext $context, array $filters, string $from, string $to)
    {
        $query = DB::table('fees_collect as fc')
            ->join('tblstudent as s', 's.id', '=', 'fc.student_id')
            ->where('fc.sub_institute_id', $context->selectedInstituteId)
            ->where('fc.is_deleted', 'N')
            ->whereBetween('fc.receiptdate', [$from, $to]);

        if (! empty($filters['student_id'])) {
            $query->where('fc.student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['payment_mode'])) {
            $query->where('fc.payment_mode', $filters['payment_mode']);
        }

        return $query;
    }

    private function otherFees(McpRequestContext $context, array $filters, string $from, string $to)
    {
        $query = DB::table('fees_paid_other as fo')
            ->leftJoin('tblstudent as s', 's.id', '=', 'fo.student_id')
            ->where('fo.sub_institute_id', $context->selectedInstituteId)
            ->where('fo.is_deleted', 'N')
            ->whereBetween('fo.receiptdate', [$from, $to]);

        if (! empty($filters['student_id'])) {
            $query->where('fo.student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['payment_mode'])) {
            $query->where('fo.payment_mode', $filters['payment_mode']);
        }

        return $query;
    }

    /**
     * Collected by payment mode, both sources together.
     *
     * @return array<int, array{mode:string, receipts:int, amount:float}>
     */
    private function byMode($regular, $other): array
    {
        $modes = [];

        foreach ($regular->selectRaw("TRIM(fc.payment_mode) AS mode, COUNT(DISTINCT CONCAT(fc.student_id, '-', fc.receipt_no)) AS n, COALESCE(SUM(fc.amount), 0) AS amt")->groupBy('mode')->get() as $row) {
            $modes[(string) $row->mode] = ['receipts' => (int) $row->n, 'amount' => (float) $row->amt];
        }

        foreach ($other->selectRaw("TRIM(fo.payment_mode) AS mode, COUNT(DISTINCT CONCAT(fo.student_id, '-', fo.reciept_id)) AS n, COALESCE(SUM(fo.actual_amountpaid), 0) AS amt")->groupBy('mode')->get() as $row) {
            $key = (string) $row->mode;
            $modes[$key] = [
                'receipts' => ($modes[$key]['receipts'] ?? 0) + (int) $row->n,
                'amount' => ($modes[$key]['amount'] ?? 0.0) + (float) $row->amt,
            ];
        }

        ksort($modes);

        return array_map(
            static fn (string $mode, array $row) => ['mode' => $mode === '' ? 'Not recorded' : $mode] + $row,
            array_keys($modes),
            array_values($modes)
        );
    }

    /** The most recent day with a receipt on or before the end of the period, for the "none found" answer. */
    private function latestBefore(McpRequestContext $context, string $to, ?int $studentId = null): ?string
    {
        $regular = DB::table('fees_collect')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('is_deleted', 'N')
            ->where('receiptdate', '<=', $to)
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->max('receiptdate');

        $other = DB::table('fees_paid_other')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('is_deleted', 'N')
            ->where('receiptdate', '<=', $to)
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->max('receiptdate');

        $latest = max((string) $regular, (string) $other);

        return $latest === '' ? null : $latest;
    }

    // ---------------------------------------------------------------- wording

    /**
     * The answer in one sentence, built only from the figures above.
     *
     * With a student it says what THAT student paid in the period — receipts actually received — and
     * nothing about what they owe or were charged.
     *
     * @param  array{from:string,to:string,label:string,single:bool}  $period
     * @param  array{student_id:int,name:string}|null  $student
     */
    private function headline(array $period, int $total, float $amount, int $otherCount, int $regularCount, ?string $latest, array $filters, ?array $student = null, int $lines = 0): string
    {
        $who = $student === null ? null : $student['name'];
        $scope = $who === null && ! empty($filters['student_id']) ? ' for that student' : '';
        $mode = ! empty($filters['payment_mode']) ? strtolower(trim((string) $filters['payment_mode'])) : null;
        $on = $period['single'] ? 'on' : 'from';

        // Asked about one payment mode: say that mode had nothing, and don't name another mode's date.
        if ($total === 0 && $mode !== null) {
            return sprintf(
                'No %s payment was found%s %s %s.',
                $mode,
                $who === null ? $scope : ' for '.$who,
                $on,
                $period['label']
            );
        }

        if ($total === 0) {
            $latestText = $latest === null
                ? ($who === null ? '' : ' No payment from '.$who.' is on record up to then.')
                : sprintf(' The latest %s on record before then was on %s.', $who === null ? 'collection' : 'payment from '.$who, Carbon::parse($latest)->format('j F Y'));

            return $who === null
                ? sprintf('No fee collection records were found for %s%s.%s', $period['label'], $scope, $latestText)
                : sprintf('No payment was found for %s %s %s.%s', $who, $on, $period['label'], $latestText);
        }

        $detail = ($lines > $total ? sprintf(' (%d term lines)', $lines) : '').($mode !== null ? sprintf(' — %s payments only', $mode) : '');
        $split = $otherCount > 0 ? sprintf(' (%d regular, %d other-fee)', $regularCount, $otherCount) : '';

        return sprintf(
            '%s%s%s across %d receipt%s %s %s%s%s%s.',
            $who === null ? '' : $who.' paid ',
            FeesReminderService::formatRupees($amount),
            $who === null ? ' collected' : '',
            $total,
            $total === 1 ? '' : 's',
            $period['single'] ? 'on' : 'from',
            $period['label'],
            $scope,
            $split,
            $detail
        );
    }

    /**
     * The one student a question is about, or the honest reason there isn't one.
     *
     * By id, or by name or enrolment number: every word of a name must appear in the student's full
     * name, so a middle name in between does not matter. Several matches are listed, never chosen from.
     *
     * @return array{student?: array{student_id:int,name:string,class:?string,enrollment_no:?string}, problem?: array<string, mixed>}
     */
    private function findStudent(McpRequestContext $context, int $studentId, string $name): array
    {
        // The class is the student's latest enrolment: tblstudent itself carries none.
        $query = DB::table('tblstudent as s')
            ->where('s.sub_institute_id', $context->selectedInstituteId)
            ->selectRaw("s.id, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name, s.enrollment_no,
                (SELECT st.name FROM tblstudent_enrollment e JOIN standard st ON st.id = e.standard_id
                  WHERE e.student_id = s.id ORDER BY e.id DESC LIMIT 1) AS standard_name");

        if ($studentId > 0) {
            $query->where('s.id', $studentId);
        } else {
            $words = array_values(array_filter(preg_split('/\s+/u', $name) ?: [], static fn ($w) => $w !== ''));

            $query->where(function ($any) use ($words, $name) {
                $any->where('s.enrollment_no', $name)->orWhere(function ($all) use ($words) {
                    foreach ($words as $word) {
                        $all->whereRaw(
                            "CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) like ?",
                            ['%'.str_replace(['%', '_'], ['\\%', '\\_'], $word).'%']
                        );
                    }
                });
            });
        }

        $rows = $query->orderBy('s.id')->limit(8)->get();
        $label = $name !== '' ? $name : 'student '.$studentId;

        if ($rows->isEmpty()) {
            return ['problem' => [
                'headline' => sprintf('I could not find a student matching "%s" in this institute, so no payments are shown.', $label),
                'student_not_found' => true,
                'note' => 'No student matched; nothing was substituted for it.',
            ]];
        }

        if ($rows->count() > 1) {
            return ['problem' => [
                'headline' => sprintf('More than one student matches "%s". Say which one — by enrolment number — and I will show what they paid.', $label),
                'ambiguous_students' => $rows->map(static fn ($r) => [
                    'student_id' => (int) $r->id,
                    'name' => trim((string) $r->student_name),
                    'class' => $r->standard_name,
                    'enrollment_no' => $r->enrollment_no,
                ])->all(),
                'note' => 'Several students matched; none was chosen.',
            ]];
        }

        $r = $rows->first();

        return ['student' => [
            'student_id' => (int) $r->id,
            'name' => trim((string) $r->student_name),
            'class' => $r->standard_name,
            'enrollment_no' => $r->enrollment_no,
        ]];
    }

    /** @return array<string, int|float> */
    private function emptySummary(): array
    {
        return [
            'total_receipts' => 0, 'total_amount' => 0.0, 'total_fine' => 0.0, 'total_discount' => 0.0,
            'other_fee_receipts' => 0, 'other_fee_amount' => 0.0, 'all_receipts' => 0, 'total_collected' => 0.0,
        ];
    }
}
