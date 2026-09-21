<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\TransportIntelligence;
use App\Http\Controllers\Brain\BrainTransportIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransportIntelligenceTest extends TestCase
{
    private function tenantWithTransport(): ?array
    {
        try {
            $row = DB::table('transport_map_student as m')
                ->join('academic_year as y', function ($join) {
                    $join->on('y.sub_institute_id', '=', 'm.sub_institute_id')
                        ->on('y.syear', '=', 'm.syear');
                })
                ->select('m.sub_institute_id', 'm.syear', DB::raw('COUNT(*) as c'))
                ->groupBy('m.sub_institute_id', 'm.syear')
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? [(string) $row->sub_institute_id, (string) $row->syear] : null;
    }

    private function payload(string $tenant, ?string $syear): array
    {
        $request = Request::create('/api/brain/'.$tenant.'/transport/intelligence', 'GET',
            $syear === null ? [] : ['syear' => $syear]);

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainTransportIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $tenant = $this->tenantWithTransport();
        if ($tenant === null) {
            $this->markTestSkipped('No transport data in this database.');
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
        $analytics = new TransportIntelligence('999999', '1900');
        $coverage = $analytics->coverage();

        $this->assertFalse($coverage['available']);
        $this->assertNotEmpty($coverage['reason']);
        $this->assertNull($analytics->position());
    }

    public function test_tenant_isolation_never_leaks_records(): void
    {
        $tenant = $this->tenantWithTransport();
        if ($tenant === null) {
            $this->markTestSkipped('No transport data in this database.');
        }

        [$tenantId, $syear] = $tenant;
        $analytics = new TransportIntelligence($tenantId, $syear);
        $pos = $analytics->position();

        if ($pos === null) {
            $this->markTestSkipped('This institute-year has no usable transport position.');
        }

        // ── DB ↔ analytics reconciliation ────────────────────────────────
        // Every headline figure is re-derived here straight from the table
        // with the same scope, so a join that silently widened the tenant or
        // year filter fails the test rather than inflating the screen.
        $rawRiders = DB::table('transport_map_student')
            ->where('sub_institute_id', $tenantId)
            ->where('syear', $syear)
            ->distinct()
            ->count('student_id');

        $this->assertEquals($rawRiders, $pos['riders'], 'Rider count does not reconcile against the raw table.');

        $rawVehicles = DB::table('transport_map_student')
            ->where('sub_institute_id', $tenantId)
            ->where('syear', $syear)
            ->where('from_bus_id', '>', 0)
            ->distinct()
            ->count('from_bus_id');

        $this->assertEquals(
            $rawVehicles,
            $pos['vehiclesInService'],
            'Vehicles in service does not reconcile against the raw table.',
        );

        $rawStops = DB::table('transport_map_student')
            ->where('sub_institute_id', $tenantId)
            ->where('syear', $syear)
            ->where('from_stop', '>', 0)
            ->distinct()
            ->count('from_stop');

        $this->assertEquals(
            $rawStops,
            $pos['stopsInService'],
            'Boarding points does not reconcile against the raw table.',
        );

        // The by-vehicle breakdown must account for exactly the students the
        // position claims, minus any with no vehicle recorded at all.
        $withoutVehicle = DB::table('transport_map_student')
            ->where('sub_institute_id', $tenantId)
            ->where('syear', $syear)
            ->where(fn ($q) => $q->whereNull('from_bus_id')->orWhere('from_bus_id', '<=', 0))
            ->distinct()
            ->count('student_id');

        $this->assertLessThanOrEqual(
            $pos['riders'],
            array_sum(array_column($analytics->byVehicle(), 'riders')) - $withoutVehicle,
            'The by-vehicle breakdown accounts for more students than travel this year.',
        );
    }

    /**
     * Records whose stated capacity their own load makes impossible are travel-
     * mode markers, not crowded buses, and must not reach a capacity figure.
     */
    public function test_implausible_capacity_records_are_excluded_from_seat_figures(): void
    {
        $tenant = $this->tenantWithTransport();
        if ($tenant === null) {
            $this->markTestSkipped('No transport data in this database.');
        }

        $analytics = new TransportIntelligence($tenant[0], $tenant[1]);

        foreach ($analytics->byVehicle() as $vehicle) {
            if (! $vehicle['capacityCredible']) {
                continue;
            }
            $this->assertNotNull($vehicle['capacity'], 'A credible vehicle must have a capacity.');
            $this->assertLessThanOrEqual(
                $vehicle['capacity'] * TransportIntelligence::IMPLAUSIBLE_LOAD_MULTIPLE,
                $vehicle['riders'],
                "{$vehicle['label']} is marked credible while carrying an impossible load.",
            );
        }

        $payload = $this->payload($tenant[0], $tenant[1]);
        $vehicles = null;
        foreach ($payload['breakdowns'] as $breakdown) {
            if ($breakdown['key'] === 'vehicles') {
                $vehicles = $breakdown;
            }
        }
        $this->assertNotNull($vehicles, 'The by-vehicle breakdown is missing.');

        foreach ($vehicles['rows'] as $row) {
            $load = $row['values']['utilization'];
            if ($load === null) {
                continue;
            }
            $this->assertLessThanOrEqual(
                TransportIntelligence::IMPLAUSIBLE_LOAD_MULTIPLE * 100,
                $load,
                "The by-vehicle breakdown shows {$row['label']} at an impossible load.",
            );
        }
    }

    /** Boarding points are named, never shown as the foreign key. */
    public function test_boarding_points_resolve_to_names(): void
    {
        $tenant = $this->tenantWithTransport();
        if ($tenant === null) {
            $this->markTestSkipped('No transport data in this database.');
        }

        $analytics = new TransportIntelligence($tenant[0], $tenant[1]);
        $stops = $analytics->byStop();

        if ($stops === []) {
            $this->markTestSkipped('This institute-year records no boarding stops.');
        }

        foreach ($stops as $stop) {
            // An unresolved stop says so in words; what it must never do is
            // present the key itself as though it were the stop's name.
            $this->assertNotSame(
                $stop['key'],
                $stop['label'],
                'A boarding point is being labelled with its own foreign key.',
            );
        }
    }

    /**
     * ONE FINDING PER PATTERN, NOT PER ROW.
     *
     * The previous implementation raised one finding per vehicle — eighty-nine
     * at the largest institute. A reader cannot act on that, so the ceiling is
     * asserted rather than left to whoever writes the next rule.
     */
    public function test_findings_are_aggregated_rather_than_one_per_vehicle(): void
    {
        $tenant = $this->tenantWithTransport();
        if ($tenant === null) {
            $this->markTestSkipped('No transport data in this database.');
        }

        $payload = $this->payload($tenant[0], $tenant[1]);

        $this->assertLessThanOrEqual(
            count($payload['ruleStatus']),
            count($payload['findings']),
            'More findings than rules means a rule is raising one finding per row again.',
        );
    }

    public function test_every_finding_carries_evidence_and_hypothesis_tag(): void
    {
        $tenant = $this->tenantWithTransport();
        if ($tenant === null) {
            $this->markTestSkipped('No transport data in this database.');
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
            $this->assertFalse($finding['causeConfirmed']);
        }
    }
}

