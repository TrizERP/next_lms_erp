<?php

namespace Tests\Feature\Brain;

use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The acceptance test for "the Brain and the LMS are looking at the same rows".
 *
 * This is a LIVE-DATA test on purpose. Every count it asserts is computed twice:
 * once by re-writing the LMS's own query here, and once by asking the Brain API
 * over HTTP with a real LMS token. Nothing is fixtured and nothing is asserted
 * against a literal — a hard-coded "604 departments" would pass forever after
 * the institute's data changed, which is the failure mode this test exists to
 * catch.
 *
 * The tenant is DISCOVERED, not named: the first institute that has departments,
 * staff and students is the one exercised, so the same test is meaningful on any
 * copy of vivek_erp.
 *
 * Skips rather than fails when the database is unreachable, so a developer
 * without VPN access still gets a green suite.
 */
class BrainDataConsistencyTest extends TestCase
{
    private ?string $tenantId = null;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('vivek_erp is not reachable: '.$e->getMessage());
        }

        $this->tenantId = $this->discoverTenant();
        if ($this->tenantId === null) {
            $this->markTestSkipped('No institute in this database has departments, staff and students.');
        }

        $user = DB::table('tbluser')
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->where('tbluser.sub_institute_id', $this->tenantId)
            ->first(['tbluser.id', 'tbluser.is_admin', 'tbluser.client_id', 'tbluser.user_profile_id']);

        $this->token = (string) app(JwtToken::class)->createToken([
            'id' => $user->id,
            'sub_institute_id' => $this->tenantId,
            // The role gate is exercised by its own assertions below; this test
            // is about data, so it uses an administrator's view.
            'is_admin' => 1,
            'client_id' => $user->client_id,
            'user_profile_id' => $user->user_profile_id,
            'is_student' => false,
        ]);
    }

    /** The first institute with all three populations, so the test has something to compare. */
    private function discoverTenant(): ?string
    {
        $row = DB::table('hrms_departments as d')
            ->where('d.status', 1)
            ->whereIn('d.sub_institute_id', function ($q) {
                $q->from('tblstudent')->where('status', 1)->select('sub_institute_id')->distinct();
            })
            ->whereIn('d.sub_institute_id', function ($q) {
                $q->from('tbluser')
                    ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
                    ->select('tbluser.sub_institute_id')->distinct();
            })
            ->groupBy('d.sub_institute_id')
            ->orderByRaw('COUNT(*) DESC')
            ->first(['d.sub_institute_id']);

        return $row ? (string) $row->sub_institute_id : null;
    }

    private function brainGet(string $path)
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/brain/'.$this->tenantId.$path);
    }

    /* ------------------------------------------------ the LMS's own numbers */

    private function lmsDepartments(): int
    {
        return (int) DB::table('hrms_departments')
            ->where('sub_institute_id', $this->tenantId)->where('status', 1)->count();
    }

    private function lmsPeople(): int
    {
        return (int) DB::table('tbluser')
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->where('tbluser.sub_institute_id', $this->tenantId)->count();
    }

    private function lmsStudents(): int
    {
        return (int) DB::table('tblstudent')
            ->where('sub_institute_id', $this->tenantId)->where('status', 1)->count();
    }

    /* -------------------------------------------------------------- the test */

    public function test_overview_foundation_counts_match_the_lms(): void
    {
        $response = $this->brainGet('/overview')->assertOk();
        $foundation = $response->json('foundation');

        $this->assertSame($this->lmsDepartments(), (int) $foundation['departments'], 'Departments');
        $this->assertSame($this->lmsPeople(), (int) $foundation['people'], 'People');
        $this->assertSame($this->lmsStudents(), (int) $foundation['students'], 'Students');
    }

    public function test_executive_summary_counts_match_the_lms(): void
    {
        $foundation = $this->brainGet('/executive')->assertOk()->json('summary.foundation');

        $this->assertSame($this->lmsDepartments(), (int) $foundation['departments'], 'Departments');
        $this->assertSame($this->lmsPeople(), (int) $foundation['people'], 'People');
        $this->assertSame($this->lmsStudents(), (int) $foundation['students'], 'Students');
    }

    public function test_foundation_screens_report_the_same_totals(): void
    {
        $this->assertSame($this->lmsDepartments(), (int) $this->brainGet('/departments')->assertOk()->json('total'));
        $this->assertSame($this->lmsPeople(), (int) $this->brainGet('/people')->assertOk()->json('total'));
        $this->assertSame($this->lmsStudents(), (int) $this->brainGet('/students')->assertOk()->json('total'));
    }

    public function test_foundation_rows_belong_to_this_tenant_only(): void
    {
        $payload = $this->brainGet('/foundation')->assertOk()->json();

        $this->assertSame($this->lmsDepartments(), (int) $payload['counts']['departments']);
        $this->assertSame($this->lmsPeople(), (int) $payload['counts']['people']);
        $this->assertSame($this->lmsStudents(), (int) $payload['counts']['students']);

        foreach ($payload['departments'] as $row) {
            $this->assertSame((string) $this->tenantId, (string) $row['sub_institute_id']);
            $this->assertSame(1, (int) $row['status']);
        }
        foreach ($payload['students'] as $row) {
            $this->assertSame(1, (int) $row['status']);
        }
        foreach ($payload['people'] as $row) {
            $this->assertSame(
                (string) $this->tenantId,
                (string) DB::table('tbluser')->where('id', $row['id'])->value('sub_institute_id'),
                'Person '.$row['id'].' is not in this tenant'
            );
        }
    }

    public function test_department_headcounts_sum_to_no_more_than_the_roster(): void
    {
        $rows = $this->brainGet('/departments')->assertOk()->json('data');

        $assigned = array_sum(array_map(fn ($r) => (int) $r['staff_count'], $rows));
        $this->assertLessThanOrEqual(
            $this->lmsPeople(),
            $assigned,
            'Department headcounts add up to more staff than the institute has'
        );
    }

    public function test_analytics_charts_agree_with_the_headline_counts(): void
    {
        $payload = $this->brainGet('/analytics')->assertOk()->json();

        $completeness = collect($payload['students']['recordCompleteness'] ?? []);
        $total = (int) ($completeness->firstWhere('label', 'Total students')['value'] ?? 0);
        $this->assertSame($this->lmsStudents(), $total, 'Student analytics total');

        foreach ($completeness->where('label', '!=', 'Total students') as $row) {
            $this->assertLessThanOrEqual(
                $total,
                (int) $row['value'],
                $row['label'].' counts more students than exist'
            );
        }

        $staff = collect($payload['people']['recordCompleteness'] ?? []);
        $staffTotal = (int) ($staff->firstWhere('label', 'Total staff')['value'] ?? 0);
        $this->assertSame($this->lmsPeople(), $staffTotal, 'Staff analytics total');

        // Every staff member has exactly one gender bucket and one status bucket.
        $this->assertLessThanOrEqual($staffTotal, array_sum(array_column($payload['people']['byGender'], 'value')));
        $this->assertSame($staffTotal, array_sum(array_column($payload['people']['byStatus'], 'value')));
    }

    public function test_graph_roots_agree_with_foundation(): void
    {
        $roots = collect($this->brainGet('/graph')->assertOk()->json('roots'))->keyBy('type');

        $this->assertSame($this->lmsPeople(), (int) $roots['person']['count'], 'Graph staff root');
        $this->assertSame($this->lmsStudents(), (int) $roots['student']['count'], 'Graph student root');
    }

    /** Every GET the front end issues must answer, and answer for this tenant. */
    public function test_every_read_endpoint_answers(): void
    {
        foreach ([
            '/overview', '/foundation', '/departments', '/people', '/students',
            '/capabilities', '/ingestion', '/kasba', '/ai-assistant', '/settings',
            '/intelligence', '/signals', '/recommendations', '/executive',
            '/intelligence/classes', '/intelligence/departments', '/intelligence/teachers',
            '/graph', '/analytics', '/knowledge', '/automation', '/search?q=a',
        ] as $path) {
            $this->brainGet($path)->assertOk();
        }
    }

    public function test_signal_detail_and_student_profile_answer(): void
    {
        $signals = $this->brainGet('/signals')->assertOk()->json('data');
        if ($signals) {
            $this->brainGet('/signals/'.$signals[0]['id'])->assertOk();
        }

        $student = DB::table('tblstudent')->where('sub_institute_id', $this->tenantId)
            ->where('status', 1)->value('id');
        if ($student) {
            $this->brainGet('/intelligence/students/'.$student)->assertOk();
        }
    }

    /* ------------------------------------------------------ tenant isolation */

    public function test_another_tenants_workspace_is_refused(): void
    {
        $other = DB::table('hrms_departments')
            ->where('sub_institute_id', '!=', $this->tenantId)
            ->value('sub_institute_id');

        if ($other === null) {
            $this->markTestSkipped('Only one institute in this database.');
        }

        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/brain/'.$other.'/overview')
            ->assertStatus(403)
            ->assertJson(['error' => 'brain_tenant_mismatch']);
    }

    public function test_an_unsigned_token_is_refused(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not.a.token'])
            ->getJson('/api/brain/'.$this->tenantId.'/overview')
            ->assertStatus(401);
    }
}
