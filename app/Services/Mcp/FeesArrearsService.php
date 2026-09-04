<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Who owes money, across a cohort rather than one student at a time.
 *
 * The gap this closes is narrow and was costing real answers. `fees.getPending` needs a
 * `student_id`, so the only fee questions the lifecycle could answer were about somebody
 * already named — while `module_keywords` routes "defaulter" and "defaulters" to the fees
 * module at weight 3.5. "Who are the fee defaulters?" therefore classified correctly,
 * reached a module bound only to a per-student lookup and a receipts report, and could not
 * be answered by either.
 *
 * ## Why this does not compute arrears itself
 *
 * It would be shorter to sum `fees_breackoff` against `fees_collect` here. It would also
 * be a second, competing definition of what a family owes — and fee logic on this estate
 * carries previous-year carry-forward, quota, per-institute special cases and cancellation
 * rules that took years to settle. A governed answer that disagreed with the fees screen
 * by a hundred rupees would be worse than no answer at all, because somebody would act on
 * it.
 *
 * So every figure here comes from `FeesPendingService`, which goes through the same
 * `studentFeesDetailAPI` → `getBk()` path the fees screen itself uses. One definition of
 * arrears, used twice.
 *
 * ## The cost, stated rather than hidden
 *
 * Reuse means one call per student, so the cohort is bounded hard (25 by default, 100
 * ceiling) and the result always reports `cohort_size`, `students_checked` and
 * `truncated`. A tool that quietly examined the first 25 of 3,435 children and answered
 * "3 defaulters" would be stating a school-wide fact on a 0.7% sample. Saying which
 * students were examined is what makes the number honest.
 */
class FeesArrearsService
{
    private const DEFAULT_LIMIT = 25;

    private const MAX_LIMIT = 100;

    public function __construct(private readonly FeesPendingService $pending)
    {
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function arrears(McpRequestContext $context, array $arguments): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);
        $minimum = max((float) ($arguments['min_amount'] ?? 0), 0);

        [$studentIds, $cohortSize] = $this->cohort($context, $arguments, $limit);

        if ($studentIds === []) {
            return ToolResult::success(
                'fees.arrears',
                'No students matched that filter, so there was nothing to check.',
                [
                    'students_with_arrears' => [],
                    'defaulter_count' => 0,
                    'total_outstanding' => 0.0,
                    'cohort_size' => $cohortSize,
                    'students_checked' => 0,
                    'truncated' => false,
                ]
            );
        }

        $defaulters = [];
        $failed = [];
        $total = 0.0;

        foreach ($studentIds as $studentId) {
            try {
                $result = $this->pending->getPending($context, ['student_id' => $studentId]);
            } catch (Throwable $exception) {
                // One student's fee record failing must not take the cohort with it. The
                // failure is counted and named rather than swallowed, so a partial answer
                // never reads as a complete one.
                $failed[] = ['student_id' => $studentId, 'error' => $exception->getMessage()];

                continue;
            }

            $data = $result['data'] ?? [];
            $items = is_array($data['pending_items'] ?? null) ? $data['pending_items'] : [];
            $outstanding = $this->sumRemaining($items);

            if ($outstanding <= 0 || $outstanding < $minimum) {
                continue;
            }

            $student = is_array($data['student'] ?? null) ? $data['student'] : [];
            $total += $outstanding;

            $defaulters[] = [
                'student_id' => $studentId,
                'student_name' => $student['student_name'] ?? null,
                'enrollment_no' => $student['enrollment_no'] ?? null,
                'standard_name' => $student['standard_name'] ?? null,
                'outstanding' => round($outstanding, 2),
                'pending_items' => count($items),
            ];
        }

        usort($defaulters, static fn (array $a, array $b) => $b['outstanding'] <=> $a['outstanding']);

        $truncated = $cohortSize > count($studentIds);

        // Every lookup failing is not a finding of no arrears.
        //
        // The two states produce identical numbers — nought defaulters, nought owed — and
        // mean opposite things. Reporting a total lookup failure as "nobody owes anything"
        // is the fee-module version of reporting a crashed agent run as "no student is at
        // risk", and it is the kind of sentence a school would act on.
        if ($failed !== [] && count($failed) === count($studentIds)) {
            return ToolResult::failure(
                'fees.arrears',
                sprintf(
                    'None of the %d student%s could be checked — every arrears lookup failed. '
                    . 'This is not a finding that nobody owes money; nothing is known either way.',
                    count($studentIds),
                    count($studentIds) === 1 ? '' : 's'
                ),
                'ARREARS_LOOKUP_FAILED'
            );
        }

        return ToolResult::success(
            'fees.arrears',
            $this->summarise($defaulters, count($studentIds), $cohortSize, $truncated, count($failed)),
            [
                'students_with_arrears' => $defaulters,
                'defaulter_count' => count($defaulters),
                'total_outstanding' => round($total, 2),
                'cohort_size' => $cohortSize,
                'students_checked' => count($studentIds),
                'truncated' => $truncated,
                'failed' => $failed,
                'min_amount' => $minimum,
                'academic_year' => $context->academicYear,
                'basis' => 'Every figure comes from the same per-student arrears calculation the fees '
                    . 'screen uses (fees_collect_controller::getBk). Nothing here recomputes what is owed.',
            ]
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * The students to check, and how many were eligible altogether.
     *
     * Enrolment drives the cohort rather than `tblstudent`, because a fee is owed against
     * a placement — and the optional class filters are only expressible there.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{0:array<int, int>, 1:int}
     */
    private function cohort(McpRequestContext $context, array $arguments, int $limit): array
    {
        $query = DB::table('tblstudent_enrollment as e')
            ->join('tblstudent as s', 's.id', '=', 'e.student_id')
            ->where('e.sub_institute_id', $context->selectedInstituteId)
            ->where(function ($inner) {
                $inner->whereNull('s.student_inactive')->orWhere('s.student_inactive', '!=', 1);
            });

        if (! empty($arguments['standard_id'])) {
            $query->where('e.standard_id', (int) $arguments['standard_id']);
        }

        if (! empty($arguments['section_id'])) {
            $query->where('e.section_id', (int) $arguments['section_id']);
        }

        // The academic year narrows the cohort only when it actually selects anybody.
        //
        // Same disagreement between roll and current term that StudentScope::placement()
        // has to handle: an institute whose enrolments predate the resolved year would
        // otherwise produce an empty cohort and a confident "no defaulters".
        if ($context->academicYear !== null) {
            $scoped = (clone $query)->where('e.syear', $context->academicYear);

            if ($scoped->exists()) {
                $query = $scoped;
            }
        }

        $ids = $query->distinct()->orderBy('e.student_id')->pluck('e.student_id');

        $all = $ids->map(static fn ($id) => (int) $id)->unique()->values();

        return [$all->take($limit)->all(), $all->count()];
    }

    /**
     * Total still owed across the pending rows.
     *
     * `remain` arrives as a string from the fee tables and occasionally carries thousands
     * separators, so it is normalised rather than cast straight to float — `(float)
     * "1,200"` is 1.0, which would understate a debt by three orders of magnitude.
     *
     * @param  array<int, mixed>  $items
     */
    private function sumRemaining(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $row = is_object($item) ? (array) $item : (array) $item;
            $raw = $row['remain'] ?? 0;

            $total += (float) str_replace([',', ' '], '', (string) $raw);
        }

        return $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $defaulters
     */
    private function summarise(
        array $defaulters,
        int $checked,
        int $cohortSize,
        bool $truncated,
        int $failed
    ): string {
        // A partial failure narrows what the number covers, so it is stated in the same
        // breath as the number rather than left in the payload for a caller to notice.
        $caveats = [];

        if ($truncated) {
            $caveats[] = sprintf(
                'this covers the first %d of %d students in scope, not the whole school',
                $checked,
                $cohortSize
            );
        }

        if ($failed > 0) {
            $caveats[] = sprintf(
                '%d lookup%s failed and %s excluded',
                $failed,
                $failed === 1 ? '' : 's',
                $failed === 1 ? 'is' : 'are'
            );
        }

        $tail = $caveats === [] ? '' : ' — ' . implode('; ', $caveats) . '.';

        $answered = $checked - $failed;

        if ($defaulters === []) {
            return sprintf(
                'None of the %d student%s checked has an outstanding balance',
                $answered,
                $answered === 1 ? '' : 's'
            ) . ($tail ?: '.');
        }

        return sprintf(
            '%d of %d student%s checked %s money outstanding',
            count($defaulters),
            $answered,
            $answered === 1 ? '' : 's',
            count($defaulters) === 1 ? 'has' : 'have'
        ) . ($tail ?: '.');
    }
}
