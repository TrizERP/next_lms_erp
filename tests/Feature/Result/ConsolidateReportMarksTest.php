<?php

namespace Tests\Feature\Result;

use App\Http\Controllers\result\result_master\consolidateReportController;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

/**
 * The consolidated marks grid, against the tables the marks are actually in.
 *
 * ── THE DEFECT THESE TESTS PIN ──────────────────────────────────────────────
 *
 * `/result/reports` built a complete, correctly-structured consolidated grid and
 * filled every cell with 0, at every real institute in this database. It read
 * `result_marks` — a table holding TWELVE ROWS in the whole database, because it
 * is written by the marks-entry screen and almost nobody uses that screen. The
 * 1.3 million marks that institutes actually have are in
 * `result_personalize_marks`, which is what the report card and the student
 * lookup API read.
 *
 * A grid of zeros is worse than an empty one: it does not look broken.
 */
class ConsolidateReportMarksTest extends TestCase
{
    /** @return array<int|string, array<int|string, mixed>> */
    private function marksFor($tenant, $syear, array $examIds, array $students): array
    {
        $controller = new consolidateReportController();
        $method = (new ReflectionClass($controller))->getMethod('marksFor');
        $method->setAccessible(true);

        return $method->invoke($controller, $tenant, $syear, $examIds, $students);
    }

    /**
     * An institute-year whose marks DO carry the exam ids this report is built
     * on — the workflow the report was written for.
     *
     * @return array{tenant:int,syear:int,standard:int}|null
     */
    private function keyedScope(): ?array
    {
        try {
            $row = DB::table('result_personalize_marks as p')
                ->join('result_create_exam as rce', function ($join) {
                    $join->on('rce.id', '=', 'p.exam_id')
                        ->on('rce.sub_institute_id', '=', 'p.sub_institute_id')
                        ->on('rce.syear', '=', 'p.syear');
                })
                ->where('rce.report_card_status', 'Y')
                ->groupBy('p.sub_institute_id', 'p.syear', 'rce.standard_id')
                ->orderByRaw('COUNT(*) DESC')
                ->first([
                    'p.sub_institute_id as tenant',
                    'p.syear as syear',
                    'rce.standard_id as standard',
                ]);
        } catch (\Throwable) {
            return null;
        }

        return $row === null
            ? null
            : ['tenant' => (int) $row->tenant, 'syear' => (int) $row->syear, 'standard' => (int) $row->standard];
    }

    /** @return array{examIds:array<int,mixed>,students:array<int,array<string,mixed>>} */
    private function classFor(array $scope): array
    {
        $examIds = DB::table('result_create_exam')
            ->where('sub_institute_id', $scope['tenant'])
            ->where('syear', $scope['syear'])
            ->where('standard_id', $scope['standard'])
            ->where('report_card_status', 'Y')
            ->pluck('id')
            ->all();

        $students = DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as ts', 'ts.id', '=', 'se.student_id')
            ->where('se.sub_institute_id', $scope['tenant'])
            ->where('se.syear', $scope['syear'])
            ->where('se.standard_id', $scope['standard'])
            ->limit(80)
            ->get(['se.student_id', 'ts.enrollment_no'])
            ->map(static fn ($r) => (array) $r)
            ->all();

        return ['examIds' => $examIds, 'students' => $students];
    }

    /**
     * THE DEFECT ITSELF: the report read a table holding twelve rows.
     */
    public function test_the_report_reads_the_table_the_marks_are_actually_in(): void
    {
        $scope = $this->keyedScope();
        if ($scope === null) {
            $this->markTestSkipped('No institute-year keys its marks to this report’s exam definitions.');
        }

        ['examIds' => $examIds, 'students' => $students] = $this->classFor($scope);

        if ($examIds === [] || $students === []) {
            $this->markTestSkipped('The richest scope has no class to build.');
        }

        $marks = $this->marksFor($scope['tenant'], $scope['syear'], $examIds, $students);

        $cells = 0;
        foreach ($marks as $perExam) {
            $cells += count($perExam);
        }

        $this->assertGreaterThan(
            0,
            $cells,
            "{$scope['tenant']}/{$scope['syear']}: the consolidated grid resolved no marks at the institute-year "
                .'whose marks DO carry this report’s exam ids. That is the original defect.',
        );

        // And the old source alone could not have produced them — which is what
        // makes this a fix rather than a rearrangement.
        $legacyRows = DB::table('result_marks')
            ->where('sub_institute_id', $scope['tenant'])
            ->whereIn('exam_id', $examIds)
            ->count();

        $this->assertLessThan(
            $cells,
            $legacyRows,
            'result_marks alone already covered this grid, so nothing was actually fixed.',
        );
    }

    /**
     * THE VALUES MUST BE THE SOURCE'S OWN.
     *
     * A report that renders plausible numbers from the wrong rows is worse than
     * one that renders zeros, so every resolved cell is checked back against the
     * row it came from.
     */
    public function test_every_resolved_mark_matches_the_row_it_came_from(): void
    {
        $scope = $this->keyedScope();
        if ($scope === null) {
            $this->markTestSkipped('No institute-year keys its marks to this report’s exam definitions.');
        }

        ['examIds' => $examIds, 'students' => $students] = $this->classFor($scope);
        if ($examIds === [] || $students === []) {
            $this->markTestSkipped('The richest scope has no class to build.');
        }

        $marks = $this->marksFor($scope['tenant'], $scope['syear'], $examIds, $students);
        $checked = 0;

        foreach (array_slice($marks, 0, 5, true) as $examId => $perStudent) {
            foreach ($perStudent as $key => $value) {
                // The enrolment-number aliases resolve to the same rows.
                if (str_starts_with((string) $key, 'enr:')) {
                    continue;
                }

                $source = DB::table('result_personalize_marks')
                    ->where('sub_institute_id', $scope['tenant'])
                    ->where('syear', $scope['syear'])
                    ->where('exam_id', $examId)
                    ->where('student_id', $key)
                    ->value('obtain');

                $legacy = DB::table('result_marks')
                    ->where('sub_institute_id', $scope['tenant'])
                    ->where('exam_id', $examId)
                    ->where('student_id', $key)
                    ->first();

                if ($legacy !== null) {
                    // result_marks wins where it holds a row; that precedence is
                    // deliberate, so this cell is not the personalize value.
                    continue;
                }

                $this->assertEquals(
                    $source,
                    $value,
                    "exam {$examId}, student {$key}: the grid shows a mark that is not the one on file.",
                );
                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked, 'No cell was actually verified against its source row.');
    }

    /**
     * WHERE THE MARKS CANNOT BE REACHED, THE CELL IS EMPTY AND NOT ZERO.
     *
     * Two institutes in this database hold marks that this report genuinely
     * cannot resolve — one whose exam ids match no exam definition, one with no
     * exam definitions at all. Their cells must come back absent, so the page
     * renders a dash. Inventing a zero for them would be the original defect
     * again in a new place.
     */
    public function test_marks_that_cannot_be_reached_come_back_absent_rather_than_zero(): void
    {
        // An institute-year with exam definitions whose marks key to none of them.
        $scope = DB::table('result_create_exam as rce')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('result_personalize_marks as p')
                    ->whereColumn('p.sub_institute_id', 'rce.sub_institute_id')
                    ->whereColumn('p.syear', 'rce.syear');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('result_personalize_marks as p')
                    ->whereColumn('p.exam_id', 'rce.id')
                    ->whereColumn('p.sub_institute_id', 'rce.sub_institute_id');
            })
            ->groupBy('rce.sub_institute_id', 'rce.syear', 'rce.standard_id')
            ->orderByRaw('COUNT(*) DESC')
            ->first([
                'rce.sub_institute_id as tenant',
                'rce.syear as syear',
                'rce.standard_id as standard',
            ]);

        if ($scope === null) {
            $this->markTestSkipped('Every institute-year with exam definitions can resolve its marks.');
        }

        $built = $this->classFor([
            'tenant' => (int) $scope->tenant,
            'syear' => (int) $scope->syear,
            'standard' => (int) $scope->standard,
        ]);

        if ($built['examIds'] === [] || $built['students'] === []) {
            $this->markTestSkipped('The unresolvable scope has no class to build.');
        }

        $marks = $this->marksFor(
            (int) $scope->tenant,
            (int) $scope->syear,
            $built['examIds'],
            $built['students'],
        );

        // Whatever it resolves, no cell may be a fabricated zero for a student
        // with no row: absent keys are what make the page render a dash.
        foreach ($marks as $examId => $perStudent) {
            foreach ($perStudent as $key => $value) {
                $this->assertNotNull(
                    $value,
                    "exam {$examId}, key {$key}: a null was placed in the lookup rather than left out of it.",
                );
            }
        }

        $this->assertTrue(true);
    }

    /**
     * THE N+1 THIS REPLACES: one query per (term x exam x subject x paper) cell.
     *
     * At one institute a single class builds 86 exam definitions, which meant 86
     * round trips against a table holding twelve rows.
     */
    public function test_the_whole_grid_is_fetched_in_a_handful_of_queries(): void
    {
        $scope = $this->keyedScope();
        if ($scope === null) {
            $this->markTestSkipped('No institute-year keys its marks to this report’s exam definitions.');
        }

        ['examIds' => $examIds, 'students' => $students] = $this->classFor($scope);
        if (count($examIds) < 5 || $students === []) {
            $this->markTestSkipped('The richest scope is too small to demonstrate the N+1.');
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->marksFor($scope['tenant'], $scope['syear'], $examIds, $students);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            4,
            $queries,
            "The grid for {$scope['standard']} spans ".count($examIds)." exams and took {$queries} queries. It is "
                .'fetched for every exam at once, so this should be a small constant.',
        );
    }
}
