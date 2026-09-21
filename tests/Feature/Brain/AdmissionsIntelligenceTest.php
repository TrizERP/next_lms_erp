<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\AdmissionsIntelligence;
use App\Http\Controllers\Brain\BrainAdmissionsIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionsIntelligenceTest extends TestCase
{
    private function tenantWithAdmissions(): ?array
    {
        try {
            $row = DB::table('admission_registration_v1')
                ->select('sub_institute_id', DB::raw('YEAR(created_at) as syear'), DB::raw('COUNT(*) as c'))
                ->whereNotNull('sub_institute_id')
                ->where('sub_institute_id', '>', 0)
                ->groupBy('sub_institute_id', DB::raw('YEAR(created_at)'))
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? ['tenant' => (string) $row->sub_institute_id, 'syear' => (string) $row->syear] : null;
    }

    private function payload(string $tenant, ?string $syear = null): array
    {
        $uri = '/api/brain/' . $tenant . '/admissions/intelligence' . ($syear ? '?syear=' . $syear : '');
        $request = Request::create($uri, 'GET');

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainAdmissionsIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $info = $this->tenantWithAdmissions();
        if ($info === null) {
            $this->markTestSkipped('No admissions data in this database.');
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
        $info = $this->tenantWithAdmissions();
        if ($info === null) {
            $this->markTestSkipped('No admissions data.');
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
        $info = $this->tenantWithAdmissions();
        if ($info === null) {
            $this->markTestSkipped('No admissions data.');
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
}

