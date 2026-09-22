<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\StudentIntelligence;
use App\Http\Controllers\Brain\BrainStudentIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentIntelligenceTest extends TestCase
{
    private function tenantWithStudents(): ?array
    {
        try {
            $row = DB::table('tblstudent_enrollment as e')
                ->join('academic_year as y', function ($join) {
                    $join->on('y.sub_institute_id', '=', 'e.sub_institute_id')
                        ->on('y.syear', '=', 'e.syear');
                })
                ->select('e.sub_institute_id', 'e.syear', DB::raw('COUNT(*) as c'))
                ->groupBy('e.sub_institute_id', 'e.syear')
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? [(string) $row->sub_institute_id, (string) $row->syear] : null;
    }

    private function payload(string $tenant, ?string $syear): array
    {
        $request = Request::create('/api/brain/'.$tenant.'/student/intelligence', 'GET',
            $syear === null ? [] : ['syear' => $syear]);

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainStudentIntelligenceController())->index($request)->getContent(), true);
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $tenant = $this->tenantWithStudents();
        if ($tenant === null) {
            $this->markTestSkipped('No student enrollment data in this database.');
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
        $analytics = new StudentIntelligence('999999', '1900');
        $coverage = $analytics->coverage();

        $this->assertFalse($coverage['available']);
        $this->assertNotEmpty($coverage['reason']);
        $this->assertNull($analytics->position());
    }

    public function test_tenant_isolation_never_leaks_records(): void
    {
        $tenant = $this->tenantWithStudents();
        if ($tenant === null) {
            $this->markTestSkipped('No student enrollment data in this database.');
        }

        [$tenantId, $syear] = $tenant;
        $analytics = new StudentIntelligence($tenantId, $syear);
        $pos = $analytics->position();

        if ($pos === null) {
            $this->markTestSkipped('This institute-year has no usable roll.');
        }

        // ── DB <-> analytics reconciliation ──────────────────────────────
        // Re-derived straight from the table with the same scope, so a join
        // that widened the tenant or year filter fails here rather than
        // inflating the screen.
        $rawStudents = DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $tenantId)
            ->where('syear', $syear)
            ->distinct()
            ->count('student_id');

        $this->assertSame($rawStudents, $pos['students'], 'The roll does not reconcile against the raw table.');

        $rawClasses = DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $tenantId)
            ->where('syear', $syear)
            ->distinct()
            ->count(DB::raw('CONCAT(standard_id, "-", section_id)'));

        $this->assertSame($rawClasses, $pos['classes'], 'The class count does not reconcile against the raw table.');

        // The class breakdown must account for exactly the roll.
        $this->assertSame(
            $pos['students'],
            array_sum(array_column($analytics->byClass(), 'students')),
            'The by-class breakdown does not account for the whole roll.',
        );
    }

    /**
     * A CLASS IS A STANDARD AND A SECTION TOGETHER.
     *
     * Dividing the roll by the count of distinct sections is what produced an
     * average class of 390 children at the largest institute, and a finding
     * naming a standard as though its several sections shared one room.
     */
    public function test_a_class_is_a_standard_and_a_section(): void
    {
        $tenant = $this->tenantWithStudents();
        if ($tenant === null) {
            $this->markTestSkipped('No student enrollment data in this database.');
        }

        $analytics = new StudentIntelligence($tenant[0], $tenant[1]);
        $pos = $analytics->position();
        if ($pos === null) {
            $this->markTestSkipped('This institute-year has no usable roll.');
        }

        $this->assertGreaterThanOrEqual(
            $pos['sections'],
            $pos['classes'],
            'There cannot be fewer classes than sections.',
        );
        $this->assertGreaterThanOrEqual(
            $pos['standards'],
            $pos['classes'],
            'There cannot be fewer classes than standards.',
        );

        if ($pos['medianClassSize'] !== null) {
            // A median class larger than the largest real class would mean the
            // roll is being divided by something other than the class.
            $largest = max(array_column($analytics->byClass(), 'students'));
            $this->assertLessThanOrEqual($largest, $pos['medianClassSize']);
            $this->assertSame($largest, $pos['largestClassSize']);
        }
    }

    /** Sensitive columns are never read, however convenient a breakdown of them would be. */
    public function test_sensitive_student_columns_are_never_read(): void
    {
        // Comments are stripped first: the file's own header EXPLAINS that it
        // does not read these columns, and a naive substring search would fail
        // on the explanation rather than on the code.
        $source = '';
        foreach (token_get_all((string) file_get_contents(app_path('Brain/Intelligence/StudentIntelligence.php'))) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $source .= is_array($token) ? $token[1] : $token;
        }

        foreach (['religion', 'reserve_categorey', 'subcast', 'adharnumber', 'password'] as $column) {
            $this->assertStringNotContainsString(
                $column,
                $source,
                "StudentIntelligence reads '{$column}', which must not reach an Intelligence payload.",
            );
        }

        // `cast` needs a quote-delimited check: the word appears inside
        // "broadcast" and in PHP type casts, and neither is the caste column.
        $this->assertDoesNotMatchRegularExpression(
            '/[\'"`]cast[\'"`]/',
            $source,
            'StudentIntelligence reads the caste column, which must not reach an Intelligence payload.',
        );
    }

    public function test_every_finding_carries_evidence_and_hypothesis_tag(): void
    {
        $tenant = $this->tenantWithStudents();
        if ($tenant === null) {
            $this->markTestSkipped('No student enrollment data in this database.');
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
