<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\AcademicIntelligence;
use App\Http\Controllers\Brain\BrainAcademicIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicIntelligenceTest extends TestCase
{
    private function tenantWithTimetable(): ?array
    {
        try {
            $row = DB::table('timetable as t')
                ->join('academic_year as y', function ($join) {
                    $join->on('y.sub_institute_id', '=', 't.sub_institute_id')
                        ->on('y.syear', '=', 't.syear');
                })
                ->select('t.sub_institute_id', 't.syear', DB::raw('COUNT(*) as c'))
                ->groupBy('t.sub_institute_id', 't.syear')
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? [(string) $row->sub_institute_id, (string) $row->syear] : null;
    }

    private function payload(string $tenant, ?string $syear): array
    {
        $request = Request::create('/api/brain/'.$tenant.'/academic/intelligence', 'GET',
            $syear === null ? [] : ['syear' => $syear]);

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainAcademicIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $tenant = $this->tenantWithTimetable();
        if ($tenant === null) {
            $this->markTestSkipped('No timetable data in this database.');
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
        $analytics = new AcademicIntelligence('999999', '1900');
        $coverage = $analytics->coverage();

        $this->assertFalse($coverage['available']);
        $this->assertNotEmpty($coverage['reason']);
        $this->assertNull($analytics->position());
    }

    public function test_tenant_isolation_never_leaks_records(): void
    {
        $tenant = $this->tenantWithTimetable();
        if ($tenant === null) {
            $this->markTestSkipped('No timetable data in this database.');
        }

        [$tenantId, $syear] = $tenant;
        $analytics = new AcademicIntelligence($tenantId, $syear);
        $pos = $analytics->position();

        if ($pos !== null) {
            $rawCount = DB::table('timetable')
                ->where('sub_institute_id', $tenantId)
                ->where('syear', $syear)
                ->count();

            $this->assertEquals($rawCount, $pos['totalPeriods']);
        }
    }

    public function test_every_finding_carries_evidence_and_hypothesis_tag(): void
    {
        $tenant = $this->tenantWithTimetable();
        if ($tenant === null) {
            $this->markTestSkipped('No timetable data in this database.');
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

