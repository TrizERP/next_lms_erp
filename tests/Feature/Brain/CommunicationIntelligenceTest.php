<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\CommunicationIntelligence;
use App\Http\Controllers\Brain\BrainCommunicationIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunicationIntelligenceTest extends TestCase
{
    private function tenantWithCommunication(): ?array
    {
        try {
            $row = DB::table('parent_communication as pc')
                ->join('academic_year as ay', function ($j) {
                    $j->on('ay.sub_institute_id', '=', 'pc.sub_institute_id')
                      ->on('ay.syear', '=', 'pc.syear');
                })
                ->select('pc.sub_institute_id', 'pc.syear', DB::raw('COUNT(*) as c'))
                ->whereNotNull('pc.sub_institute_id')
                ->where('pc.sub_institute_id', '>', 0)
                ->groupBy('pc.sub_institute_id', 'pc.syear')
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? ['tenant' => (string) $row->sub_institute_id, 'syear' => (string) $row->syear] : null;
    }

    private function payload(string $tenant, ?string $syear = null): array
    {
        $uri = '/api/brain/' . $tenant . '/communication/intelligence' . ($syear ? '?syear=' . $syear : '');
        $request = Request::create($uri, 'GET');

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainCommunicationIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $info = $this->tenantWithCommunication();
        if ($info === null) {
            $this->markTestSkipped('No communication data with matching academic year in this database.');
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
        $info = $this->tenantWithCommunication();
        if ($info === null) {
            $this->markTestSkipped('No communication data.');
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
        $info = $this->tenantWithCommunication();
        if ($info === null) {
            $this->markTestSkipped('No communication data.');
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

