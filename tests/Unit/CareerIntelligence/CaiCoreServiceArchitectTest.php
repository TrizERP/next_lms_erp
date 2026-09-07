<?php

namespace Tests\Unit\CareerIntelligence;

use App\CareerIntelligence\CaiCoreService;
use App\CareerIntelligence\Ingestion\ErpSubjectEnrolmentAdapter;
use App\Services\Neo4jService;
use DateTimeImmutable;
use Laudis\Neo4j\Types\Date;
use ReflectionClass;
use Tests\TestCase;

/**
 * Proves the fix reaches all the way to a real Architect alignment result for
 * student_id=284976 (standard_id=42), not just DeclaredPlan resolution.
 *
 * Runs against the real Neo4j 'cai' graph (only OCC-ARCHITECT is seeded) and
 * the real DeclaredPlan resolved by ErpSubjectEnrolmentAdapter after the
 * STEM Resources exclusion. Does not insert/mutate any student_aspirations
 * row — it exercises CaiCoreService::CAI_CORE_QUERY directly (via reflection,
 * since it's a private const) with the same parameters
 * CaiCoreService::evaluate() would build from a real Architect aspiration, so
 * no test data needs writing to or cleaning up from the shared ERP database.
 */
class CaiCoreServiceArchitectTest extends TestCase
{
    private const STUDENT_ID = '284976';
    private const ACADEMIC_YEAR = '2021';

    public function test_architect_alignment_for_student_284976_reports_only_physics_missing(): void
    {
        $plan = (new ErpSubjectEnrolmentAdapter())->fetch(self::STUDENT_ID, self::ACADEMIC_YEAR);
        $this->assertTrue($plan->resolved, 'Precondition: DeclaredPlan must resolve before alignment can be evaluated.');

        $query = (new ReflectionClass(CaiCoreService::class))->getConstant('CAI_CORE_QUERY');

        $row = app(Neo4jService::class)->run($query, [
            'occupation_id' => 'OCC-ARCHITECT',
            'current_stream' => $plan->stream,
            'student_subjects' => $plan->subjects,
            'board' => 'CBSE',
            'grade' => $plan->grade,
            'academic_year' => self::ACADEMIC_YEAR,
            'today' => new Date(intdiv((new DateTimeImmutable())->getTimestamp(), 86400)),
        ])->first();

        $this->assertNotNull($row, 'OCC-ARCHITECT must exist in the Neo4j graph for this test to be meaningful.');

        $breakPoint = $row->get('break_point');

        $this->assertSame('MISALIGNED', $row->get('alignment_status'));
        $this->assertSame(['PHYSICS'], $breakPoint->get('missing_subjects')->toArray());
        $this->assertSame('SCIENCE', $breakPoint->get('required_stream'));
    }
}
