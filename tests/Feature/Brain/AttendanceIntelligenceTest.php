<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\AttendanceIntelligence;
use App\Http\Controllers\Brain\BrainAttendanceIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceIntelligenceTest extends TestCase
{
    private function tenantWithAttendance(): ?array
    {
        try {
            $row = DB::table('result_student_attendance_master as a')
                ->join('academic_year as y', function ($join) {
                    $join->on('y.sub_institute_id', '=', 'a.sub_institute_id')
                        ->on('y.syear', '=', 'a.syear');
                })
                ->select('a.sub_institute_id', 'a.syear', DB::raw('COUNT(*) as c'))
                ->where(function ($q) {
                    $q->where('a.attendance', '>', 0)
                      ->orWhere('a.percentage', '>', 0);
                })
                ->groupBy('a.sub_institute_id', 'a.syear')
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? [(string) $row->sub_institute_id, (string) $row->syear] : null;
    }

    /**
     * An institute-year that holds attendance ROWS but almost no days present.
     *
     * The failure this guards is specific: one institute's year holds 6,736 rows
     * and three of them record a day present. The old gate passed it, and the
     * screen reported a 36.9% institute attendance rate under a headline reading
     * "3 students evaluated".
     *
     * @return array{0:string,1:string}|null
     */
    private function tenantWithUnenteredAttendance(): ?array
    {
        try {
            $row = DB::table('result_student_attendance_master')
                ->select('sub_institute_id', 'syear', DB::raw('COUNT(*) as rows_total'), DB::raw(
                    'SUM(CASE WHEN attendance > 0 THEN 1 ELSE 0 END) as recorded'
                ))
                ->groupBy('sub_institute_id', 'syear')
                ->havingRaw('COUNT(*) >= 200')
                ->havingRaw('SUM(CASE WHEN attendance > 0 THEN 1 ELSE 0 END) BETWEEN 1 AND COUNT(*) * 0.2')
                ->orderByDesc('rows_total')
                ->first();
        } catch (\Throwable) {
            return null;
        }

        return $row ? [(string) $row->sub_institute_id, (string) $row->syear] : null;
    }

    private function payload(string $tenant, ?string $syear): array
    {
        $request = Request::create('/api/brain/'.$tenant.'/attendance/intelligence', 'GET',
            $syear === null ? [] : ['syear' => $syear]);

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainAttendanceIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $tenant = $this->tenantWithAttendance();
        if ($tenant === null) {
            $this->markTestSkipped('No attendance data in this database.');
        }

        $payload = $this->payload($tenant[0], $tenant[1]);

        foreach ([
            'tenantId', 'organization', 'source', 'academicYear', 'coverage',
            'freshness', 'execution', 'summary', 'position', 'breakdowns',
            'findings', 'priorities', 'recommendations', 'decisionTrail',
            'learning', 'dataQuality', 'ruleStatus',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "Payload is missing '{$key}'");
        }

        $this->assertEquals($tenant[0], $payload['tenantId']);
        $this->assertEquals($tenant[1], $payload['academicYear']['syear']);
    }

    public function test_empty_year_reports_honest_coverage_state(): void
    {
        $analytics = new AttendanceIntelligence('999999', '1900');
        $coverage = $analytics->coverage();

        $this->assertFalse($coverage['available']);
        $this->assertNotEmpty($coverage['reason']);
        $this->assertNull($analytics->position());
    }

    public function test_tenant_isolation_never_leaks_records(): void
    {
        $tenant = $this->tenantWithAttendance();
        if ($tenant === null) {
            $this->markTestSkipped('No attendance data in this database.');
        }

        [$tenantId, $syear] = $tenant;
        $analytics = new AttendanceIntelligence($tenantId, $syear);
        $pos = $analytics->position();

        if ($pos !== null) {
            // The upper bound is every student this institute-year records days
            // present for. The figure may be lower — a student whose rows carry
            // no working days anywhere has no denominator and is excluded — but
            // it can never be higher without the scope having leaked.
            $ceiling = DB::table('result_student_attendance_master')
                ->where('sub_institute_id', $tenantId)
                ->where('syear', $syear)
                ->where('attendance', '>', 0)
                ->distinct()
                ->count('student_id');

            $this->assertLessThanOrEqual(
                $ceiling,
                $pos['students'],
                'More students were counted than this institute-year records attendance for.',
            );
            $this->assertGreaterThan(0, $pos['students']);
        }
    }

    public function test_every_finding_carries_evidence_and_hypothesis_tag(): void
    {
        $tenant = $this->tenantWithAttendance();
        if ($tenant === null) {
            $this->markTestSkipped('No attendance data in this database.');
        }

        $payload = $this->payload($tenant[0], $tenant[1]);

        $this->assertIsArray($payload['findings']);
        $this->assertIsArray($payload['ruleStatus']);
        $this->assertNotEmpty($payload['ruleStatus']);

        foreach ($payload['findings'] as $finding) {
            $this->assertArrayHasKey('id', $finding);
            $this->assertArrayHasKey('severity', $finding);
            $this->assertArrayHasKey('title', $finding);
            $this->assertArrayHasKey('whatHappened', $finding);
            $this->assertArrayHasKey('whyItMatters', $finding);
            $this->assertArrayHasKey('evidence', $finding);
            $this->assertArrayHasKey('confidence', $finding);
            $this->assertFalse($finding['causeConfirmed'], 'Unconfirmed causes must be labelled as hypothesis');
        }
    }

    /**
     * A year with rows but no days present must report UNAVAILABLE.
     *
     * Not "0% attendance", and not a rate over whoever happened to be filled in:
     * "nobody has entered attendance" and "attendance is poor" are opposite
     * conclusions from the same empty column, and the reason has to carry the
     * counts that distinguish them.
     */
    public function test_a_year_with_rows_but_no_days_present_is_unavailable(): void
    {
        $scope = $this->tenantWithUnenteredAttendance();
        if ($scope === null) {
            $this->markTestSkipped('Every institute-year in this database has attendance entered.');
        }

        $analytics = new AttendanceIntelligence($scope[0], $scope[1]);
        $coverage = $analytics->coverage();

        $this->assertFalse(
            $coverage['available'],
            'A year whose attendance was never entered is being reported as usable.',
        );
        $this->assertNull($analytics->position(), 'A position was computed for a year with no attendance.');

        // The reason must carry the figures, so a reader can tell this apart
        // from a year with no rows at all.
        $this->assertStringContainsString((string) $coverage['counts']['rows'], $coverage['reason']);
        $this->assertStringContainsString(
            (string) $coverage['counts']['rowsWithDaysPresent'],
            $coverage['reason'],
        );
        $this->assertFalse($coverage['sources']['daysPresentEntered']);
    }

    /**
     * Every headline figure re-derived straight from the table with the same
     * scope, so a join that widened the tenant or year filter fails here rather
     * than inflating the screen.
     */
    public function test_position_reconciles_against_raw_rows(): void
    {
        $scope = $this->tenantWithAttendance();
        if ($scope === null) {
            $this->markTestSkipped('No attendance data in this database.');
        }

        $analytics = new AttendanceIntelligence($scope[0], $scope[1]);
        $position = $analytics->position();
        if ($position === null) {
            $this->markTestSkipped('This institute-year has no usable attendance position.');
        }

        $raw = DB::table('result_student_attendance_master as a')
            ->leftJoin('result_working_day_master as w', function ($join) {
                $join->on('w.sub_institute_id', '=', 'a.sub_institute_id')
                    ->on('w.syear', '=', 'a.syear')
                    ->on('w.standard', '=', 'a.standard')
                    ->on('w.term_id', '=', 'a.term_id');
            })
            ->where('a.sub_institute_id', $scope[0])
            ->where('a.syear', $scope[1])
            ->where('a.attendance', '>', 0)
            ->whereRaw('COALESCE(NULLIF(a.working_day, 0), w.total_working_day) > 0')
            ->distinct()
            ->count('a.student_id');

        $this->assertSame($raw, $position['students'], 'Student count does not reconcile against the raw rows.');

        $onRoll = DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $scope[0])
            ->where('syear', $scope[1])
            ->distinct()
            ->count('student_id');

        if ($onRoll > 0) {
            $this->assertSame($onRoll, $position['studentsOnRoll'], 'Roll does not reconcile against enrolment.');
        }

        // A rate can never exceed 100%: days present are capped at the term's
        // working days before they are summed.
        if ($position['attendanceRate'] !== null) {
            $this->assertLessThanOrEqual(100.0, $position['attendanceRate']);
            $this->assertGreaterThanOrEqual(0.0, $position['attendanceRate']);
        }
        $this->assertLessThanOrEqual($position['totalWorkingDays'], $position['totalPresentDays']);
    }

    /** The register being smaller than the roll is stated, not silently absorbed. */
    public function test_roll_coverage_is_reported(): void
    {
        $scope = $this->tenantWithAttendance();
        if ($scope === null) {
            $this->markTestSkipped('No attendance data in this database.');
        }

        $payload = $this->payload($scope[0], $scope[1]);
        if (! $payload['coverage']['available']) {
            $this->markTestSkipped('This institute-year has no usable attendance.');
        }

        $keys = array_column($payload['position']['metrics'], 'key');
        $this->assertContains('rollCoverage', $keys, 'Roll coverage is not on the position strip.');

        $checks = array_column($payload['dataQuality']['checks'], 'key');
        $this->assertContains('roll_not_in_register', $checks, 'The roll gap is not in the record checks.');
    }

    /** No class below the cohort floor may be compared against the institute. */
    public function test_small_classes_are_never_compared_against_the_institute(): void
    {
        $scope = $this->tenantWithAttendance();
        if ($scope === null) {
            $this->markTestSkipped('No attendance data in this database.');
        }

        $analytics = new AttendanceIntelligence($scope[0], $scope[1]);
        if ($analytics->position() === null) {
            $this->markTestSkipped('This institute-year has no usable attendance position.');
        }

        $small = [];
        foreach ($analytics->byStandard() as $class) {
            if ($class['students'] < AttendanceIntelligence::MIN_CLASS_COHORT) {
                $small[$class['key']] = $class['label'];
            }
        }

        if ($small === []) {
            $this->markTestSkipped('Every class at this institute-year clears the cohort floor.');
        }

        $payload = $this->payload($scope[0], $scope[1]);
        foreach ($payload['findings'] as $finding) {
            if (($finding['rule'] ?? null) !== 'class_attendance_gap') {
                continue;
            }
            foreach (array_keys($small) as $key) {
                $this->assertStringNotContainsString(
                    "-{$key}-",
                    $finding['id'],
                    "A class below the cohort floor was compared against the institute: {$small[$key]}",
                );
            }
        }
    }
}
