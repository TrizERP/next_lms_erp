<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\HomeworkIntelligence;
use App\Http\Controllers\Brain\BrainHomeworkIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeworkIntelligenceTest extends TestCase
{
    private function tenantWithHomework(): ?array
    {
        try {
            $row = DB::table('homework as h')
                ->join('academic_year as ay', function ($j) {
                    $j->on('ay.sub_institute_id', '=', 'h.sub_institute_id')
                      ->on('ay.syear', '=', 'h.syear');
                })
                ->select('h.sub_institute_id', 'h.syear', DB::raw('COUNT(*) as c'))
                ->whereNotNull('h.sub_institute_id')
                ->where('h.sub_institute_id', '>', 0)
                ->groupBy('h.sub_institute_id', 'h.syear')
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? ['tenant' => (string) $row->sub_institute_id, 'syear' => (string) $row->syear] : null;
    }

    private function payload(string $tenant, ?string $syear = null): array
    {
        $uri = '/api/brain/' . $tenant . '/homework/intelligence' . ($syear ? '?syear=' . $syear : '');
        $request = Request::create($uri, 'GET');

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainHomeworkIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $info = $this->tenantWithHomework();
        if ($info === null) {
            $this->markTestSkipped('No homework data with matching academic year in this database.');
        }

        $payload = $this->payload($info['tenant'], $info['syear']);

        foreach ([
            'tenantId', 'organization', 'source', 'academicYear', 'coverage',
            'freshness', 'execution', 'summary', 'position', 'breakdowns',
            'findings', 'priorities', 'recommendations', 'decisionTrail',
            'learning', 'dataQuality', 'ruleStatus',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "Payload is missing '{$key}'");
        }

        $this->assertEquals($info['tenant'], $payload['tenantId']);
        $this->assertTrue($payload['coverage']['available']);
        $this->assertIsArray($payload['coverage']['counts']);
        $this->assertNotEmpty($payload['coverage']['counts'], 'Coverage must say what it was computed from.');
        $this->assertGreaterThan(0, max($payload['coverage']['counts']));
    }

    public function test_findings_have_traceable_evidence(): void
    {
        $info = $this->tenantWithHomework();
        if ($info === null) {
            $this->markTestSkipped('No homework data.');
        }

        $payload = $this->payload($info['tenant'], $info['syear']);

        foreach ($payload['findings'] as $finding) {
            $this->assertArrayHasKey('id', $finding);
            $this->assertArrayHasKey('rule', $finding);
            $this->assertArrayHasKey('whatHappened', $finding);
            $this->assertArrayHasKey('severityLabel', $finding);
            $this->assertIsArray($finding['confidence']);
            $this->assertArrayHasKey('band', $finding['confidence']);
            $this->assertArrayHasKey('severity', $finding);
            $this->assertArrayHasKey('evidence', $finding);
            $this->assertArrayHasKey('causeConfirmed', $finding);
            $this->assertFalse($finding['causeConfirmed'], 'Findings must not falsely assert confirmed root causes.');
        }
    }

    public function test_empty_tenant_reports_unavailable_coverage_honestly(): void
    {
        $payload = $this->payload('999999999', '2099');

        $this->assertFalse($payload['coverage']['available']);
        $this->assertNotEmpty($payload['coverage']['reason']);
        $this->assertFalse($payload['coverage']['available']);
        $this->assertNotEmpty($payload['coverage']['reason'], 'An unavailable block must say why.');
        $this->assertEmpty($payload['findings']);
    }

    public function test_data_quality_runs_against_records(): void
    {
        $info = $this->tenantWithHomework();
        if ($info === null) {
            $this->markTestSkipped('No homework data.');
        }

        $payload = $this->payload($info['tenant'], $info['syear']);

        $dq = $payload['dataQuality'];
        // The canonical shape is {available, reason, checks[]}. A bare list of
        // checks is what made the section render "unavailable" while holding them.
        $this->assertArrayHasKey('available', $dq);
        $this->assertArrayHasKey('reason', $dq);
        $this->assertArrayHasKey('checks', $dq);
        $this->assertIsBool($dq['available']);

        foreach ($dq['checks'] as $check) {
            foreach (['key', 'label', 'value', 'format', 'sharePercent', 'state', 'note'] as $field) {
                $this->assertArrayHasKey($field, $check, "A record check is missing '{$field}'");
            }
            $this->assertContains($check['state'], ['ok', 'attention']);
        }

        if ($dq['available'] === false) {
            $this->assertNotEmpty($dq['reason'], 'Unavailable record checks must say why.');
        }
    }

    /* ---------------------------------------------- the rebuilt invariants */

    /**
     * Every institute-year that has set homework, so both the thin and the
     * contradictory shapes are exercised against real data.
     *
     * @return array<int,array{0:string,1:string}>
     */
    private function homeworkScopes(): array
    {
        try {
            $rows = DB::table('homework')
                ->select('sub_institute_id', 'syear', DB::raw('COUNT(*) as c'))
                ->groupBy('sub_institute_id', 'syear')
                ->orderByDesc('c')
                ->limit(8)
                ->get();
        } catch (\Throwable) {
            return [];
        }

        return array_map(
            static fn ($r) => [(string) $r->sub_institute_id, (string) $r->syear],
            $rows->all(),
        );
    }

    /**
     * THE DEFECT THIS PINS: a zero reported where there is no module.
     *
     * The previous version returned `completionRate => 0.0` for an institute
     * that has never set a piece of homework. A head reading "0% completion"
     * concludes their children did not do their work.
     */
    public function test_an_institute_that_sets_no_homework_reports_null_not_zero(): void
    {
        // An institute-year with staff and students and no homework at all.
        $scope = DB::table('tbluser')
            ->whereNotIn('sub_institute_id', function ($q) {
                $q->select('sub_institute_id')->from('homework');
            })
            ->value('sub_institute_id');

        if ($scope === null) {
            $this->markTestSkipped('Every institute in this database has set homework.');
        }

        $payload = $this->payload((string) $scope, '2025');

        $this->assertFalse($payload['coverage']['available']);
        $this->assertNotEmpty($payload['coverage']['reason'], 'An unavailable block must say why.');

        foreach ($payload['position']['metrics'] ?? [] as $metric) {
            if (in_array($metric['key'], ['submissionRate', 'reviewRate', 'classReach', 'childAssignments'], true)) {
                $this->assertNull(
                    $metric['value'],
                    "{$metric['key']} is a figure about children. Where no homework exists it must be null — a zero "
                        .'reads as a claim that the work was not done.',
                );
            }
        }
    }

    /**
     * THE DEFECT THIS PINS: a class read as a standard.
     *
     * `distinctClasses` counted `standard_id` alone, so an institute with 5
     * standards across 15 sections reported 5 classes and divided every
     * per-class figure by a third of the real number. A class is (standard,
     * section) here as it is everywhere else in this system.
     */
    public function test_a_class_is_a_standard_and_its_section(): void
    {
        $scopes = $this->homeworkScopes();
        $examined = 0;

        foreach ($scopes as [$tenant, $syear]) {
            $analytics = new HomeworkIntelligence($tenant, $syear);
            if (! $analytics->coverage()['available']) {
                continue;
            }

            $standards = (int) DB::table('homework')
                ->where('sub_institute_id', $tenant)
                ->where('syear', $syear)
                ->distinct()
                ->count('standard_id');

            $classes = (int) $analytics->coverage()['counts']['classes'];

            $this->assertGreaterThanOrEqual(
                $standards,
                $classes,
                "{$tenant}/{$syear}: fewer classes than standards, so the section is being dropped",
            );
            $this->assertSame($classes, count($analytics->byClass()), "{$tenant}/{$syear}: the class breakdown and "
                .'the class count disagree');

            $examined++;
        }

        if ($examined === 0) {
            $this->markTestSkipped('No institute-year has usable homework.');
        }
    }

    /**
     * THE DEFECT THIS PINS: a submission rate drawn from a status its own dates
     * contradict.
     *
     * At one institute all 88 pieces of homework carry a submission date AND a
     * status saying they were never handed in. Reporting "0% returned" from that
     * is arithmetically correct and would send somebody to chase 44 children who
     * did their homework.
     */
    public function test_a_contradicted_submission_status_suppresses_the_submission_findings(): void
    {
        $scopes = $this->homeworkScopes();
        $examinedUnreliable = 0;
        $examinedReliable = 0;

        foreach ($scopes as [$tenant, $syear]) {
            $analytics = new HomeworkIntelligence($tenant, $syear);
            if (! $analytics->coverage()['available']) {
                continue;
            }

            $rules = new \App\Brain\Intelligence\HomeworkSignalRules($analytics, $syear);
            $raised = array_column($rules->run()['findings'], 'rule');

            if ($analytics->statusReliable()) {
                $this->assertNotContains(
                    'submission_status_unusable',
                    $raised,
                    "{$tenant}/{$syear}: the status is reliable and the contradiction rule fired anyway",
                );
                $examinedReliable++;

                continue;
            }

            // Where the status cannot be believed, the findings BUILT ON IT must
            // stand down — a screen that says both "0% returned" and "the 0%
            // cannot be believed" is worse than one that says only the second.
            foreach (['outstanding_burden', 'subject_low_submission'] as $suppressed) {
                $this->assertNotContains(
                    $suppressed,
                    $raised,
                    "{$tenant}/{$syear}: {$suppressed} fired on a submission status the dates contradict",
                );
            }

            $this->assertContains(
                'submission_status_unusable',
                $raised,
                "{$tenant}/{$syear}: the status contradicts the dates and nothing says so",
            );

            $examinedUnreliable++;
        }

        if ($examinedUnreliable + $examinedReliable === 0) {
            $this->markTestSkipped('No institute-year has usable homework.');
        }
    }

    /**
     * THE RULE THIS PINS: the second assignment table is counted, never added in.
     *
     * `lms_assignment` carries a student status AND a teacher status, so its
     * denominator means something different from `homework`'s. A combined rate
     * would be a number with two meanings.
     */
    public function test_the_second_assignment_workflow_is_counted_but_never_merged(): void
    {
        $scopes = $this->homeworkScopes();
        $examined = 0;

        foreach ($scopes as [$tenant, $syear]) {
            $analytics = new HomeworkIntelligence($tenant, $syear);
            if (! $analytics->coverage()['available']) {
                continue;
            }

            $homeworkRows = (int) DB::table('homework')
                ->where('sub_institute_id', $tenant)->where('syear', $syear)->count();

            $this->assertSame(
                $homeworkRows,
                (int) $analytics->position()['metrics']['childAssignments'],
                "{$tenant}/{$syear}: the homework count does not match the homework table, so something else has "
                    .'been added into it',
            );

            $this->assertSame(
                (int) DB::table('lms_assignment')
                    ->where('sub_institute_id', $tenant)->where('syear', $syear)->count(),
                (int) $analytics->position()['metrics']['assignments'],
                "{$tenant}/{$syear}: the separate assignment workflow is miscounted",
            );

            $examined++;
        }

        if ($examined === 0) {
            $this->markTestSkipped('No institute-year has usable homework.');
        }
    }

    /**
     * THE RULE THIS PINS: a cohort too small to describe loses its rate.
     *
     * A subject with four pieces of work returning 25% is a statement about one
     * child, and the label on the row would name the subject they take.
     */
    public function test_a_cohort_below_the_floor_keeps_its_counts_and_loses_its_rate(): void
    {
        $scopes = $this->homeworkScopes();
        $examined = 0;

        foreach ($scopes as [$tenant, $syear]) {
            $analytics = new HomeworkIntelligence($tenant, $syear);
            if (! $analytics->coverage()['available']) {
                continue;
            }

            foreach ([$analytics->bySubject(), $analytics->byClass(), $analytics->byMonth()] as $breakdown) {
                foreach ($breakdown as $row) {
                    $examined++;

                    if ($row['suppressed']) {
                        $this->assertNull(
                            $row['submissionRate'],
                            "{$tenant}/{$syear}: '{$row['label']}' is below the floor and still reports a rate",
                        );
                        $this->assertIsInt($row['set'], 'A suppressed row keeps its counts.');

                        continue;
                    }

                    $this->assertNotNull($row['submissionRate']);
                }
            }
        }

        if ($examined === 0) {
            $this->markTestSkipped('No institute-year has usable homework.');
        }
    }
}

