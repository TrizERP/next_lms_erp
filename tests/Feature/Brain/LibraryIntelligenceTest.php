<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\LibraryIntelligence;
use App\Http\Controllers\Brain\BrainLibraryIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LibraryIntelligenceTest extends TestCase
{
    private function tenantWithLibrary(): ?array
    {
        try {
            $row = DB::table('library_book_circulations as c')
                ->join('academic_year as y', function ($join) {
                    $join->on('y.sub_institute_id', '=', 'c.sub_institute_id')
                        ->on('y.syear', '=', 'c.syear');
                })
                ->select('c.sub_institute_id', 'c.syear', DB::raw('COUNT(*) as c'))
                ->whereNotNull('c.syear')
                ->where('c.syear', '!=', '')
                ->groupBy('c.sub_institute_id', 'c.syear')
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? [(string) $row->sub_institute_id, (string) $row->syear] : null;
    }

    private function payload(string $tenant, ?string $syear): array
    {
        $request = Request::create('/api/brain/'.$tenant.'/library/intelligence', 'GET',
            $syear === null ? [] : ['syear' => $syear]);

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainLibraryIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $tenant = $this->tenantWithLibrary();
        if ($tenant === null) {
            $this->markTestSkipped('No library circulation data in this database.');
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
        $analytics = new LibraryIntelligence('999999', '1900');
        $coverage = $analytics->coverage();

        $this->assertFalse($coverage['available']);
        $this->assertNotEmpty($coverage['reason']);
        $this->assertNull($analytics->position());
    }

    public function test_tenant_isolation_never_leaks_records(): void
    {
        $tenant = $this->tenantWithLibrary();
        if ($tenant === null) {
            $this->markTestSkipped('No library circulation data in this database.');
        }

        [$tenantId, $syear] = $tenant;
        $analytics = new LibraryIntelligence($tenantId, $syear);
        $pos = $analytics->position();

        if ($pos !== null) {
            $rawCount = DB::table('library_book_circulations')
                ->where('sub_institute_id', $tenantId)
                ->where('syear', $syear)
                ->count();

            $this->assertEquals($rawCount, $pos['totalCirculations']);
        }
    }

    public function test_every_finding_carries_evidence_and_hypothesis_tag(): void
    {
        $tenant = $this->tenantWithLibrary();
        if ($tenant === null) {
            $this->markTestSkipped('No library circulation data in this database.');
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

