<?php

namespace Tests\Feature\AI\Attendance;

use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\Attendance\Risk\LowAttendanceDetector;
use App\Services\Mcp\AttendanceInsightService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The detector, against this estate's own register.
 *
 * NO FIXTURE IS CREATED AND NOTHING IS WRITTEN. The assertions are about the RULES the
 * detector applies, phrased so they hold whatever the data happens to be: a school with
 * no marked attendance passes them by finding nothing, and a school with a full register
 * passes them by finding something that obeys the rules. A test that seeded its own
 * students would be testing the seed.
 *
 * WHAT IS ACTUALLY BEING PINNED
 *
 * Three things, and each of them is a way this could go quietly wrong:
 *
 *   1. The figures come from `AttendanceInsightService`, not from a second query. A
 *      detector with its own SQL would be a second opinion about a child's record.
 *   2. A student with too few marked days is never raised. "We have not recorded enough
 *      to say" and "this child does not attend" look identical in a count and mean
 *      opposite things.
 *   3. The institute comes from the scope. A signal for another school's student is the
 *      one failure that cannot be walked back.
 */
class LowAttendanceDetectorTest extends TestCase
{
    /** The scope every read in this file runs under. */
    private function scopeFor(int $instituteId, ?int $year): McpRequestContext
    {
        return new McpRequestContext(
            userId: 1,
            role: 'admin',
            selectedInstituteId: $instituteId,
            allowedInstituteIds: [$instituteId],
            userProfileId: 1,
            clientId: null,
            academicYear: $year,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );
    }

    /**
     * The institute and year this estate actually has attendance for.
     *
     * Chosen from the data rather than hard-coded, so the file keeps working on an estate
     * whose demo institute is a different number — and skips honestly on one with no
     * attendance at all rather than asserting against an empty table and calling it a
     * pass.
     *
     * @return array{0:int, 1:int|null}
     */
    private function busiestCohort(): array
    {
        if (! Schema::hasTable('attendance_student')) {
            $this->markTestSkipped('This estate does not record attendance.');
        }

        $row = DB::table('attendance_student')
            ->selectRaw('sub_institute_id, syear, COUNT(*) AS rows_held')
            ->groupBy('sub_institute_id', 'syear')
            ->orderByDesc('rows_held')
            ->first();

        if ($row === null) {
            $this->markTestSkipped('No attendance has been marked on this estate.');
        }

        return [(int) $row->sub_institute_id, $row->syear === null ? null : (int) $row->syear];
    }

    public function test_every_signal_it_raises_is_below_the_bar_it_was_asked_for(): void
    {
        [$institute, $year] = $this->busiestCohort();

        $detector = app(LowAttendanceDetector::class);
        $scope = $this->scopeFor($institute, $year);
        $arguments = ['days' => 365, 'limit' => 200, 'min_attendance_rate' => 75];

        $signals = $detector->detect($scope, $arguments);
        $floor = $detector->minimumRate($arguments);

        $this->assertSame(0.75, $floor, 'A bar given as 75 should be read as 75%, not 7500%.');

        foreach ($signals as $signal) {
            $this->assertInstanceOf(DetectedSignal::class, $signal);
            $this->assertSame('attendance_low_rate', $signal->signalKey);
            $this->assertSame('student', $signal->subjectEntityKey);

            $rate = $signal->components['attendance_rate'] ?? null;
            $this->assertIsNumeric($rate);
            $this->assertLessThanOrEqual(
                $floor,
                (float) $rate,
                "A student attending above the bar was raised: {$signal->subjectLabel}"
            );

            // A signal with nothing to cite cannot become a case, and a case with nothing
            // to cite cannot become a recommendation anybody should approve.
            $this->assertTrue($signal->hasEvidence(), 'A signal was raised with no evidence.');
        }
    }

    /**
     * Raising a bar can only ever raise the count.
     *
     * The cheapest way to get this wrong is to compare a percentage against a fraction,
     * which reverses the comparison and reports the best-attending students as the worst.
     */
    public function test_a_higher_bar_never_finds_fewer_students(): void
    {
        [$institute, $year] = $this->busiestCohort();

        $detector = app(LowAttendanceDetector::class);
        $scope = $this->scopeFor($institute, $year);

        $strict = count($detector->detect($scope, ['days' => 365, 'limit' => 200, 'min_attendance_rate' => 50]));
        $loose = count($detector->detect($scope, ['days' => 365, 'limit' => 200, 'min_attendance_rate' => 95]));

        $this->assertGreaterThanOrEqual($strict, $loose);
    }

    /**
     * The figures the detector reports are the figures the tool reports.
     *
     * Same scope, same window: the coverage it publishes has to agree with the service
     * the chat and the Attendance screens read, or the AI and the register disagree about
     * the same children.
     */
    public function test_its_coverage_agrees_with_the_attendance_service(): void
    {
        [$institute, $year] = $this->busiestCohort();

        $scope = $this->scopeFor($institute, $year);
        $arguments = ['days' => 365, 'limit' => 200];

        $coverage = app(LowAttendanceDetector::class)->coverage($scope, $arguments);
        $overview = app(AttendanceInsightService::class)->overview($scope, $arguments);

        $this->assertSame((int) $overview['students_judged'], $coverage['judged']);
        $this->assertSame((int) $overview['students_with_insufficient_data'], $coverage['insufficient']);
        $this->assertSame($coverage['judged'] + $coverage['insufficient'], $coverage['cohort']);
    }

    /**
     * An unjudgeable student is never raised.
     *
     * The service refuses to state a rate below its own minimum of coded days, and the
     * detector has to honour that refusal rather than treating the absent rate as zero.
     */
    public function test_students_with_too_few_marked_days_are_excluded_not_failed(): void
    {
        [$institute, $year] = $this->busiestCohort();

        $scope = $this->scopeFor($institute, $year);
        $arguments = ['days' => 365, 'limit' => 200, 'min_attendance_rate' => 100];

        $signals = app(LowAttendanceDetector::class)->detect($scope, $arguments);
        $overview = app(AttendanceInsightService::class)->overview($scope, $arguments);

        // Even with the bar at 100%, only judged students can be raised.
        $this->assertLessThanOrEqual((int) $overview['students_judged'], count($signals));

        $judgedIds = array_column($overview['students'], 'student_id');

        foreach ($signals as $signal) {
            $this->assertContains(
                (int) $signal->subjectId,
                array_map('intval', $judgedIds),
                'A student the service could not judge was raised as a signal.'
            );
        }
    }

    /**
     * Two institutes, two cohorts, no overlap.
     *
     * Skipped rather than faked on an estate that only holds one school's attendance:
     * asserting isolation against a single tenant proves nothing.
     */
    public function test_one_institute_cannot_see_another_institutes_students(): void
    {
        if (! Schema::hasTable('attendance_student')) {
            $this->markTestSkipped('This estate does not record attendance.');
        }

        $cohorts = DB::table('attendance_student')
            ->selectRaw('sub_institute_id, syear, COUNT(*) AS rows_held')
            ->groupBy('sub_institute_id', 'syear')
            ->orderByDesc('rows_held')
            ->limit(2)
            ->get();

        if ($cohorts->count() < 2 || $cohorts[0]->sub_institute_id === $cohorts[1]->sub_institute_id) {
            $this->markTestSkipped('Only one institute has attendance on this estate.');
        }

        $detector = app(LowAttendanceDetector::class);
        $seen = [];

        foreach ($cohorts as $cohort) {
            $scope = $this->scopeFor((int) $cohort->sub_institute_id, $cohort->syear === null ? null : (int) $cohort->syear);

            $seen[(int) $cohort->sub_institute_id] = array_map(
                static fn (DetectedSignal $signal) => (int) $signal->subjectId,
                $detector->detect($scope, ['days' => 365, 'limit' => 200, 'min_attendance_rate' => 100])
            );
        }

        $ids = array_values($seen);

        $this->assertSame(
            [],
            array_intersect($ids[0], $ids[1]),
            'The same student was raised for two different institutes.'
        );
    }
}
