<?php

namespace Tests\Feature\Brain;

use App\Http\Controllers\Brain\BrainCrossModuleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The cross-module endpoint against the live schema. Asserts invariants, never a specific figure; skips
 * when the database or a tenant with both attendance and marks is unavailable.
 */
class CrossModuleIntelligenceTest extends TestCase
{
    private function fetchInsights(string $tenant, ?string $syear): array
    {
        $request = Request::create("/api/brain/{$tenant}/cross-module/intelligence", 'GET', $syear === null ? [] : ['syear' => $syear]);
        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);

        return json_decode((new BrainCrossModuleController())->index($request)->getContent(), true);
    }

    /** @return array{0:string,1:string}|null */
    private function tenantWithBoth(): ?array
    {
        $found = null;
        try {
            $rows = DB::table('result_student_attendance_master')
                ->select('sub_institute_id', 'syear', DB::raw('COUNT(*) c'))
                ->groupBy('sub_institute_id', 'syear')->orderByDesc('c')->limit(30)->get();
            foreach ($rows as $r) {
                if (DB::table('result_personalize_marks')->where('sub_institute_id', $r->sub_institute_id)->where('syear', $r->syear)->exists()) {
                    $found ??= [(string) $r->sub_institute_id, (string) $r->syear];
                    $status = $this->fetchInsights((string) $r->sub_institute_id, (string) $r->syear)['insights'][0]['status'];
                    if ($status === 'supported') {
                        return [(string) $r->sub_institute_id, (string) $r->syear];
                    }
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return $found;
    }

    public function test_real_institute_returns_a_well_formed_honest_insight(): void
    {
        $t = $this->tenantWithBoth();
        $t === null && $this->markTestSkipped('No institute with both attendance and marks.');

        $payload = $this->fetchInsights($t[0], $t[1]);
        $insight = $payload['insights'][0];

        $this->assertSame($t[0], $payload['tenantId']);
        $this->assertContains($insight['status'], ['supported', 'insufficient_data', 'unavailable']);
        $this->assertNotEmpty($insight['limitations']);
        $this->assertNotEmpty($insight['sources']);
        $this->assertNotEmpty($payload['notImplemented']);

        if ($insight['status'] === 'supported') {
            $this->assertGreaterThanOrEqual(6, $insight['metrics']['classesCompared']);
            $this->assertSame($insight['metrics']['classesCompared'], count($insight['rows']));
            foreach ($insight['rows'] as $row) {
                $this->assertGreaterThanOrEqual(0, $row['attendanceRate']);
                $this->assertLessThanOrEqual(100, $row['attendanceRate']);
                $this->assertGreaterThanOrEqual(0, $row['resultPercentage']);
            }
            $this->assertStringNotContainsString('because', strtolower($insight['explanation']), 'no causal wording');
        }
    }

    public function test_tenants_cannot_see_each_other(): void
    {
        $t = $this->tenantWithBoth();
        $t === null && $this->markTestSkipped('No institute with both attendance and marks.');

        $own = $this->fetchInsights($t[0], $t[1]);
        $other = $this->fetchInsights('999999991', $t[1]);

        $this->assertSame('unavailable', $other['insights'][0]['status'], 'an institute with no data sees none of another one');
        $this->assertSame([], $other['insights'][0]['rows']);
        $this->assertNotSame(json_encode($own['insights'][0]['rows']), json_encode($other['insights'][0]['rows']));
        $this->assertFalse($other['modules'][0]['available']);
    }

    public function test_an_unknown_year_is_answered_with_the_year_actually_used_and_says_so(): void
    {
        $t = $this->tenantWithBoth();
        $t === null && $this->markTestSkipped('No institute with both attendance and marks.');

        // AcademicYear::resolve falls back to the institute's current year; the payload must name it.
        $p = $this->fetchInsights($t[0], '1900');
        $used = $p['academicYear']['syear'];
        $this->assertNotSame('1900', $used);
        $this->assertSame("Academic year {$used}", $p['insights'][0]['period']);
    }
}
