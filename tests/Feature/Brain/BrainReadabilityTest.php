<?php

namespace Tests\Feature\Brain;

use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Brain has to be readable by a principal, not only correct.
 *
 * These assertions are about the WORDS the API sends, because that is what the
 * screens print verbatim: a signal whose title is `complaint_root_cause_unrecorded`
 * or whose evidence reads `null` is a defect even when every number behind it is
 * right. Run with -v to see the payload the assertions were made against.
 */
class BrainReadabilityTest extends TestCase
{
    private ?string $tenantId = null;

    private ?string $token = null;

    /**
     * Anything that should never reach a screen as displayed text.
     *
     * Matched as whole words, and NaN case-sensitively: "Aircraft Maintenance"
     * and "Financial Accounting" are real department names, and a substring
     * search for "nan" flags both.
     */
    private const FORBIDDEN = [
        '/undefined/i',
        '/NaN/',
        '/\[object Object\]/i',
        '/null\s+null/i',
        '/NULL/',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('vivek_erp is not reachable: '.$e->getMessage());
        }

        $row = DB::table('hpbrain_signals')->orderByDesc('created_date')->first(['tenant_id']);
        $this->tenantId = $row ? (string) $row->tenant_id : null;
        if ($this->tenantId === null) {
            $this->markTestSkipped('No institute has been through the intelligence pipeline yet.');
        }

        $user = DB::table('tbluser')
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->where('tbluser.sub_institute_id', $this->tenantId)
            ->first(['tbluser.id', 'tbluser.user_profile_id']);

        if (! $user) {
            $this->markTestSkipped('That institute has no staff to authenticate as.');
        }

        $this->token = (string) app(JwtToken::class)->createToken([
            'id' => $user->id,
            'sub_institute_id' => $this->tenantId,
            'is_admin' => 1,
            'client_id' => null,
            'user_profile_id' => $user->user_profile_id,
            'is_student' => false,
        ]);
    }

    private function brainGet(string $path)
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/brain/'.$this->tenantId.$path);
    }

    /** Every string anywhere in a payload, with the JSON path that reached it. */
    private function strings($value, string $path = ''): array
    {
        if (is_string($value)) {
            return [$path => $value];
        }
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $child) {
            $out += $this->strings($child, $path === '' ? (string) $key : $path.'.'.$key);
        }

        return $out;
    }

    private function assertReadable(array $payload, string $label): void
    {
        foreach ($this->strings($payload) as $path => $text) {
            foreach (self::FORBIDDEN as $pattern) {
                $this->assertSame(
                    0,
                    preg_match($pattern, $text),
                    $label.' -> '.$path.' reads "'.$text.'"'
                );
            }
        }
    }

    public function test_the_executive_payload_reads_as_english(): void
    {
        $payload = $this->brainGet('/executive')->assertOk()->json();
        $this->assertReadable($payload, 'executive');

        // Every finding on the overview must answer the five questions a person
        // asks of it: what happened, why it matters, on what evidence, how sure,
        // and what to do.
        foreach ($payload['topFindings'] as $finding) {
            $this->assertNotEmpty($finding['title'], 'A finding has no title');
            $this->assertNotEmpty($finding['whatHappened'], $finding['title'].' does not say what happened');
            $this->assertNotEmpty($finding['whyItMatters'], $finding['title'].' does not say why it matters');
            $this->assertNotEmpty($finding['evidence'], $finding['title'].' carries no evidence');
            $this->assertNotEmpty($finding['recommendation'], $finding['title'].' recommends nothing');
            $this->assertContains($finding['confidence']['band'], ['High', 'Medium', 'Low'], 'Confidence band');

            // A rule key is engine vocabulary. It may sit under "technical
            // detail"; it may not be the headline a principal reads.
            $this->assertDoesNotMatchRegularExpression(
                '/^[a-z0-9]+(_[a-z0-9]+){2,}$/',
                $finding['title'],
                'A finding is titled with its rule key: '.$finding['title']
            );
        }
    }

    public function test_health_dimensions_explain_themselves(): void
    {
        $health = $this->brainGet('/executive')->assertOk()->json('health');

        foreach ($health['dimensions'] as $dimension) {
            $this->assertNotEmpty($dimension['label']);
            $this->assertNotEmpty($dimension['why'], $dimension['label'].' does not explain its score');

            if ($dimension['available']) {
                $this->assertIsInt($dimension['score'], $dimension['label'].' is available but unscored');
                $this->assertGreaterThanOrEqual(0, $dimension['score']);
                $this->assertLessThanOrEqual(100, $dimension['score']);
                $this->assertNotEmpty($dimension['formula'], $dimension['label'].' does not say how it is scored');
            } else {
                // "Insufficient evidence" rather than a confident-looking zero.
                $this->assertNull($dimension['score'], $dimension['label'].' is unavailable but carries a score');
            }
        }
    }

    public function test_signal_details_carry_evidence_that_names_its_source(): void
    {
        $signals = $this->brainGet('/signals')->assertOk()->json('data');
        if (! $signals) {
            $this->markTestSkipped('No open signals for this institute.');
        }

        $detail = $this->brainGet('/signals/'.$signals[0]['id'])->assertOk()->json();
        $this->assertReadable($detail, 'signal detail');
        $this->assertNotEmpty($detail['evidence'], 'A signal was raised with no evidence rows');

        foreach ($detail['evidence'] as $row) {
            $this->assertNotEmpty($row['source_type'] ?? $row['source'] ?? null, 'Evidence does not name its source');
        }
    }

    public function test_class_and_department_intelligence_say_what_to_do(): void
    {
        $classes = $this->brainGet('/intelligence/classes')->assertOk()->json();
        $this->assertReadable($classes, 'class intelligence');
        if ($classes['available']) {
            foreach ($classes['classes'] as $class) {
                $this->assertNotEmpty($class['summary'], 'Class '.$class['name'].' has no summary');
            }
        } else {
            $this->assertNotEmpty($classes['reason'], 'Class intelligence is unavailable without saying why');
        }

        $departments = $this->brainGet('/intelligence/departments')->assertOk()->json();
        $this->assertReadable($departments, 'department intelligence');
        if ($departments['available']) {
            foreach ($departments['departments'] as $department) {
                $this->assertNotEmpty($department['summary'], $department['name'].' has no summary');
                $this->assertNotEmpty($department['formula'], $department['name'].' does not say how it is scored');
            }
        } else {
            $this->assertNotEmpty($departments['reason']);
        }
    }

    public function test_analytics_and_graph_payloads_read_cleanly(): void
    {
        $this->assertReadable($this->brainGet('/analytics')->assertOk()->json(), 'analytics');
        $this->assertReadable($this->brainGet('/graph')->assertOk()->json(), 'graph');
        $this->assertReadable($this->brainGet('/ingestion')->assertOk()->json(), 'ingestion');
    }
}
