<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\HostelIntelligence;
use App\Http\Controllers\Brain\BrainHostelIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HostelIntelligenceTest extends TestCase
{
    private function payload(string $tenant, ?string $syear = null): array
    {
        $uri = '/api/brain/' . $tenant . '/hostel/intelligence' . ($syear ? '?syear=' . $syear : '');
        $request = Request::create($uri, 'GET');

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainHostelIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        // Test with Tenant 328 in 2025 where 6 allocations exist
        $payload = $this->payload('328', '2025');

        foreach ([
            'tenantId', 'organization', 'source', 'academicYear', 'coverage',
            'freshness', 'execution', 'summary', 'position', 'breakdowns',
            'findings', 'priorities', 'recommendations', 'decisionTrail',
            'learning', 'dataQuality', 'ruleStatus',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "Payload is missing '{$key}'");
        }

        $this->assertEquals('328', $payload['tenantId']);
    }

    public function test_insufficient_or_empty_tenant_reports_unavailable_coverage_honestly(): void
    {
        // An institute-year holding fewer allocations than the module's own
        // minimum must report unavailable and SAY SO, rather than drawing an
        // occupancy rate over a handful of rows.
        //
        // The row count is not asserted as a literal: a test that hard-codes a
        // business figure from this database fails the day somebody allocates a
        // room, which teaches whoever sees it to edit the number rather than to
        // read the assertion.
        $payload = $this->payload('1', '2021');

        $this->assertFalse($payload['coverage']['available']);

        // `totalRows` is folded into `counts.rows` by ModulePayload, which is the
        // boundary every module's coverage passes through.
        $rows = (int) $payload['coverage']['counts']['rows'];
        $this->assertLessThan(
            HostelIntelligence::MIN_ALLOCATIONS,
            $rows,
            'Coverage was refused for an institute-year that clears the minimum.',
        );

        // The REASON must name the count it refused on, rather than any
        // particular phrasing — asserting on a word teaches the next person to
        // reword the assertion instead of reading it.
        $this->assertStringContainsString(
            (string) $rows,
            $payload['coverage']['reason'],
            'An unavailable block must say how many rows it refused on.',
        );

        // NO RATE. This is the whole point of the floor: a percentage over three
        // children describes those three children.
        $encoded = json_encode($payload['position']);
        $this->assertStringNotContainsString(
            'averageRoomDensity',
            (string) $encoded,
            'A density figure was computed below the allocation floor.',
        );

        foreach ($payload['position']['metrics'] ?? [] as $metric) {
            if ($metric['key'] === 'occupancy') {
                $this->assertNull(
                    $metric['value'],
                    'An occupancy figure was produced. This schema records no capacity against a room, so any '
                        .'occupancy would have to be invented.',
                );
            }
        }

        // Findings ARE expected here and are deliberately not gated on the
        // allocation floor: a placement naming a room nobody registered is true
        // of the records whether there are three placements or three hundred.
        // What must hold is that every one of them is structural.
        foreach ($payload['findings'] as $finding) {
            $this->assertNotEmpty(
                $finding['evidence'],
                "{$finding['rule']} raised a finding below the allocation floor with no evidence",
            );
        }

        // Non-existent tenant/year
        $emptyPayload = $this->payload('999999999', '2099');
        $this->assertFalse($emptyPayload['coverage']['available']);
        $this->assertNotEmpty($emptyPayload['coverage']['reason'], 'An unavailable block must say why.');
        $this->assertEmpty(
            $emptyPayload['findings'],
            'An institute with no boarding records of any kind must raise nothing at all.',
        );
    }

    /**
     * THE DEFECT THIS PINS: an occupancy or density figure invented from nothing.
     *
     * The previous version reported "average density of 3.0 students per room",
     * computed as boarders divided by the number of distinct room ids IN THE
     * ALLOCATIONS — which is to say divided by rooms that, at every institute in
     * this database, do not exist in the room master. There is no capacity,
     * bed-count or room-type column on a room anywhere in this schema, so no
     * occupancy figure can be derived from it at all.
     */
    public function test_no_occupancy_figure_is_ever_produced(): void
    {
        foreach ([['328', '2025'], ['1', '2021'], ['133', '2025'], ['61', '2025']] as [$tenant, $syear]) {
            $payload = $this->payload($tenant, $syear);

            foreach ($payload['position']['metrics'] ?? [] as $metric) {
                if (in_array($metric['key'], ['occupancy', 'averageRoomDensity', 'utilisation'], true)) {
                    $this->assertNull(
                        $metric['value'],
                        "{$tenant}/{$syear}: {$metric['key']} carries a value. This schema records no capacity "
                            .'against a room, so the figure cannot be anything but invented.',
                    );
                }
            }

            foreach ($payload['breakdowns'] ?? [] as $breakdown) {
                foreach ($breakdown['columns'] ?? [] as $column) {
                    $this->assertNotContains(
                        $column['key'],
                        ['occupancy', 'capacity', 'density', 'utilisation'],
                        "{$tenant}/{$syear}: breakdown '{$breakdown['key']}' has an "
                            ."'{$column['key']}' column and nothing in this schema can fill it.",
                    );
                }
            }
        }
    }

    /**
     * THE RULE THIS PINS: a warden's telephone number never leaves the database.
     *
     * `hostel_master.warden_contact` is a personal number and is populated on
     * every hostel in this database. The warden's NAME being absent is a
     * finding; the number is never a figure.
     */
    public function test_the_payload_carries_no_warden_contact_number(): void
    {
        $numbers = DB::table('hostel_master')
            ->whereRaw('COALESCE(TRIM(warden_contact), "") <> ""')
            ->pluck('warden_contact', 'sub_institute_id');

        if ($numbers->isEmpty()) {
            $this->markTestSkipped('No warden contact numbers in this database.');
        }

        foreach ($numbers as $tenant => $number) {
            $encoded = (string) json_encode($this->payload((string) $tenant, '2025'));

            $this->assertStringNotContainsString(
                'warden_contact',
                $encoded,
                "Tenant {$tenant}: the warden_contact column name reached the payload.",
            );
            $this->assertStringNotContainsString(
                trim((string) $number),
                $encoded,
                "Tenant {$tenant}: a warden's telephone number reached the payload.",
            );
        }
    }

    public function test_findings_have_traceable_evidence(): void
    {
        $payload = $this->payload('328', '2025');

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
}

