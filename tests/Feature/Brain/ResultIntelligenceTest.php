<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\ResultIntelligence;
use App\Brain\Intelligence\ResultSignalRules;
use App\Http\Controllers\Brain\BrainResultIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Result Intelligence, tested against the live vivek_erp schema.
 */
class ResultIntelligenceTest extends TestCase
{
    private function tenantWithMarks(): ?array
    {
        try {
            $row = DB::table('result_personalize_marks as m')
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
        $request = Request::create('/api/brain/'.$tenant.'/result/intelligence', 'GET',
            $syear === null ? [] : ['syear' => $syear]);

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainResultIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $tenant = $this->tenantWithMarks();
        if ($tenant === null) {
            $this->markTestSkipped('No marks in this database.');
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

    public function test_empty_year_reports_unavailable_coverage_honestly(): void
    {
        $tenant = $this->tenantWithMarks();
        if ($tenant === null) {
            $this->markTestSkipped('No marks in this database.');
        }

        // Year 1900 cannot have marks
        $payload = $this->payload($tenant[0], '1900');

        $this->assertFalse($payload['coverage']['available']);
        $this->assertNotEmpty($payload['coverage']['reason']);
        $this->assertEmpty($payload['findings']);
    }

    public function test_tenant_isolation_does_not_mix_institutes(): void
    {
        $tenant = $this->tenantWithMarks();
        if ($tenant === null) {
            $this->markTestSkipped('No marks in this database.');
        }

        [$tenantId, $syear] = $tenant;
        $analytics = new ResultIntelligence($tenantId, $syear);
        $pos = $analytics->position();

        if ($pos !== null) {
            // Compare student count directly from raw DB for this tenant only
            $rawStudents = DB::table('result_personalize_marks')
                ->where('sub_institute_id', $tenantId)
                ->where('syear', $syear)
                ->distinct()
                ->count('student_name');

            $this->assertEquals($rawStudents, $pos['students']);
        }
    }

    public function test_findings_carry_evidence_and_rule_status(): void
    {
        $tenant = $this->tenantWithMarks();
        if ($tenant === null) {
            $this->markTestSkipped('No marks in this database.');
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
}

