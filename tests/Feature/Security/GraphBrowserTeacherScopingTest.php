<?php

namespace Tests\Feature\Security;

use App\Domain\K12\TeacherClassScopeService;
use App\Domain\KnowledgeGraph\GraphModuleBrowser;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Regression coverage for the fix described in
 * docs/K12_K4_K5_IMPLEMENTATION_PLAN.md's sibling work (2026-11-19): the
 * centralized Knowledge Graph (`GraphBrowserController` /
 * `GraphModuleBrowser`, `/api/ai/knowledge-graph/browser/*`) previously
 * restricted `Student` reachability by tenant only — any staff user with
 * menu rights to this screen could search/path-find/inspect ANY student in
 * the institute, not just their own assigned classes. The same gap
 * `PalTeacherScopingTest` already regression-tests for `/api/pal/*`.
 *
 * `GraphModuleBrowser` itself is mocked throughout: its real behaviour is
 * Cypher against live Neo4j, which this suite cannot fabricate disposable
 * fixtures for inside a rolled-back MySQL transaction. What IS deterministic
 * and real here is the wiring this test actually exercises end to end:
 * `GraphBrowserController::restrictedStudentIds()` resolving `null` for a
 * real administrator (via the real `AdministratorCheck`, not the unreliable
 * raw `is_admin` flag — see the `administrator-check-vs-raw-isadmin` memory)
 * and the teacher's real `class_teacher`/`timetable`-derived student set
 * (via the real `TeacherClassScopeService`, real DB rows) otherwise — and
 * that this exact value is the one passed to `GraphModuleBrowser`. The
 * Cypher-level filtering itself (does the query actually exclude an
 * out-of-scope Student node) is verified separately, against real live
 * Neo4j data, in `GraphModuleBrowserStudentFilterTest`.
 */
class GraphBrowserTeacherScopingTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;

    private int $syear;

    private int $standardId;

    private int $divisionId;

    /** @var int[] */
    private array $assignedStudentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Graph Browser Scoping Test School',
            'ShortCode' => 'GBS' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->syear = 2026;
        $this->standardId = random_int(100000, 199999);
        $this->divisionId = random_int(200000, 299999);

        foreach (range(1, 3) as $i) {
            $studentId = (int) DB::table('tblstudent')->insertGetId([
                'first_name' => "ScopedStudent{$i}",
                'last_name' => 'Test',
                'sub_institute_id' => $this->subInstituteId,
                'file_size' => '',
                'file_type' => '',
            ]);

            DB::table('tblstudent_enrollment')->insert([
                'syear' => $this->syear,
                'student_id' => $studentId,
                'standard_id' => $this->standardId,
                'section_id' => $this->divisionId,
                'sub_institute_id' => $this->subInstituteId,
            ]);

            $this->assignedStudentIds[] = $studentId;
        }
    }

    private function teacherToken(int $teacherId): string
    {
        return JWT::encode([
            'id' => $teacherId,
            'sub_institute_id' => $this->subInstituteId,
            'is_admin' => 0,
            'is_student' => false,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    private function adminToken(int $adminId): string
    {
        return JWT::encode([
            'id' => $adminId,
            'sub_institute_id' => $this->subInstituteId,
            'is_admin' => 1,
            'is_student' => false,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    private function assignTeacherToClass(int $teacherId): void
    {
        DB::table('class_teacher')->insert([
            'syear' => $this->syear,
            'sub_institute_id' => $this->subInstituteId,
            'grade_id' => 1,
            'standard_id' => $this->standardId,
            'division_id' => $this->divisionId,
            'teacher_id' => $teacherId,
            'created_at' => now(),
        ]);
    }

    /** Sorted so a Mockery arg-matcher comparison doesn't depend on SQL row order. */
    private function sortedAssignedIds(): array
    {
        $ids = $this->assignedStudentIds;
        sort($ids);

        return $ids;
    }

    private function mockBrowser(): GraphModuleBrowser
    {
        $mock = Mockery::mock(GraphModuleBrowser::class);
        $this->app->instance(GraphModuleBrowser::class, $mock);

        return $mock;
    }

    private function matchesAssignedIds(): Mockery\Matcher\Closure
    {
        $expected = $this->sortedAssignedIds();

        return Mockery::on(function ($actual) use ($expected) {
            if (! is_array($actual)) {
                return false;
            }
            $sorted = $actual;
            sort($sorted);

            return $sorted === $expected;
        });
    }

    private function matchesEmptyArray(): Mockery\Matcher\Closure
    {
        return Mockery::on(fn ($actual) => $actual === []);
    }

    // ------------------------------------------------------------------
    // search()
    // ------------------------------------------------------------------

    public function test_search_admin_is_unrestricted(): void
    {
        $adminId = random_int(600000, 699999);

        $this->mockBrowser()
            ->shouldReceive('search')
            ->once()
            ->with('', $this->subInstituteId, 30, [], null)
            ->andReturn(['term' => '', 'nodes' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson('/api/ai/knowledge-graph/browser/search')
            ->assertStatus(200);
    }

    public function test_search_assigned_teacher_is_restricted_to_their_students(): void
    {
        $teacherId = random_int(300000, 399999);
        $this->assignTeacherToClass($teacherId);

        $this->mockBrowser()
            ->shouldReceive('search')
            ->once()
            ->with('', $this->subInstituteId, 30, [], $this->matchesAssignedIds())
            ->andReturn(['term' => '', 'nodes' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson('/api/ai/knowledge-graph/browser/search')
            ->assertStatus(200);
    }

    public function test_search_unassigned_teacher_gets_empty_restriction_not_unrestricted(): void
    {
        $teacherId = random_int(500000, 599999);

        $this->mockBrowser()
            ->shouldReceive('search')
            ->once()
            ->with('', $this->subInstituteId, 30, [], $this->matchesEmptyArray())
            ->andReturn(['term' => '', 'nodes' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson('/api/ai/knowledge-graph/browser/search')
            ->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // path()
    // ------------------------------------------------------------------

    public function test_path_admin_is_unrestricted(): void
    {
        $adminId = random_int(600000, 699999);

        $this->mockBrowser()
            ->shouldReceive('findPath')
            ->once()
            ->with('Student', '1', 'Standard', '2', $this->subInstituteId, 6, [], null)
            ->andReturn(['found' => false, 'nodes' => [], 'edges' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->postJson('/api/ai/knowledge-graph/browser/path', [
                'from_label' => 'Student', 'from_id' => 1,
                'to_label' => 'Standard', 'to_id' => 2,
            ])
            ->assertStatus(200);
    }

    public function test_path_assigned_teacher_is_restricted_to_their_students(): void
    {
        $teacherId = random_int(300000, 399999);
        $this->assignTeacherToClass($teacherId);

        $this->mockBrowser()
            ->shouldReceive('findPath')
            ->once()
            ->with('Student', '1', 'Standard', '2', $this->subInstituteId, 6, [], $this->matchesAssignedIds())
            ->andReturn(['found' => false, 'nodes' => [], 'edges' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->postJson('/api/ai/knowledge-graph/browser/path', [
                'from_label' => 'Student', 'from_id' => 1,
                'to_label' => 'Standard', 'to_id' => 2,
            ])
            ->assertStatus(200);
    }

    public function test_path_unassigned_teacher_gets_empty_restriction(): void
    {
        $teacherId = random_int(500000, 599999);

        $this->mockBrowser()
            ->shouldReceive('findPath')
            ->once()
            ->with('Student', '1', 'Standard', '2', $this->subInstituteId, 6, [], $this->matchesEmptyArray())
            ->andReturn(['found' => false, 'nodes' => [], 'edges' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->postJson('/api/ai/knowledge-graph/browser/path', [
                'from_label' => 'Student', 'from_id' => 1,
                'to_label' => 'Standard', 'to_id' => 2,
            ])
            ->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // nodeDetail()
    // ------------------------------------------------------------------

    public function test_node_detail_admin_is_unrestricted(): void
    {
        $adminId = random_int(600000, 699999);

        $this->mockBrowser()
            ->shouldReceive('nodeDetail')
            ->once()
            ->with('Student', '42', $this->subInstituteId, 50, 0, null, null, null)
            ->andReturn(['node' => null, 'nodes' => [], 'edges' => [], 'total_incoming' => 0, 'total_outgoing' => 0, 'has_more' => false]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson('/api/ai/knowledge-graph/browser/node/Student/42')
            ->assertStatus(200);
    }

    public function test_node_detail_assigned_teacher_is_restricted_to_their_students(): void
    {
        $teacherId = random_int(300000, 399999);
        $this->assignTeacherToClass($teacherId);

        $this->mockBrowser()
            ->shouldReceive('nodeDetail')
            ->once()
            ->with('Student', '42', $this->subInstituteId, 50, 0, null, null, $this->matchesAssignedIds())
            ->andReturn(['node' => null, 'nodes' => [], 'edges' => [], 'total_incoming' => 0, 'total_outgoing' => 0, 'has_more' => false]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson('/api/ai/knowledge-graph/browser/node/Student/42')
            ->assertStatus(200);
    }

    public function test_node_detail_unassigned_teacher_gets_empty_restriction(): void
    {
        $teacherId = random_int(500000, 599999);

        $this->mockBrowser()
            ->shouldReceive('nodeDetail')
            ->once()
            ->with('Student', '42', $this->subInstituteId, 50, 0, null, null, $this->matchesEmptyArray())
            ->andReturn(['node' => null, 'nodes' => [], 'edges' => [], 'total_incoming' => 0, 'total_outgoing' => 0, 'has_more' => false]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson('/api/ai/knowledge-graph/browser/node/Student/42')
            ->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // graph() (label-based "graph view", non-numeric label branch)
    // ------------------------------------------------------------------

    public function test_graph_view_admin_is_unrestricted(): void
    {
        $adminId = random_int(600000, 699999);

        $this->mockBrowser()
            ->shouldReceive('graph')
            ->once()
            ->with('Student', $this->subInstituteId, 20, 250, 15, null)
            ->andReturn(['label' => 'Student', 'nodes' => [], 'edges' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson('/api/ai/knowledge-graph/browser/submodules/Student/graph')
            ->assertStatus(200);
    }

    public function test_graph_view_assigned_teacher_is_restricted_to_their_students(): void
    {
        $teacherId = random_int(300000, 399999);
        $this->assignTeacherToClass($teacherId);

        $this->mockBrowser()
            ->shouldReceive('graph')
            ->once()
            ->with('Student', $this->subInstituteId, 20, 250, 15, $this->matchesAssignedIds())
            ->andReturn(['label' => 'Student', 'nodes' => [], 'edges' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson('/api/ai/knowledge-graph/browser/submodules/Student/graph')
            ->assertStatus(200);
    }

    public function test_graph_view_unassigned_teacher_gets_empty_restriction(): void
    {
        $teacherId = random_int(500000, 599999);

        $this->mockBrowser()
            ->shouldReceive('graph')
            ->once()
            ->with('Student', $this->subInstituteId, 20, 250, 15, $this->matchesEmptyArray())
            ->andReturn(['label' => 'Student', 'nodes' => [], 'edges' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson('/api/ai/knowledge-graph/browser/submodules/Student/graph')
            ->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // Direct service test: TeacherClassScopeService::allowedStudentIds()
    // against the same real fixture rows the HTTP tests above exercise
    // through the controller — the one piece of this whole fix that is
    // pure SQL and fully deterministic.
    // ------------------------------------------------------------------

    public function test_allowed_student_ids_matches_real_enrollment_and_fails_closed_for_unassigned(): void
    {
        $teacherId = random_int(300000, 399999);
        $this->assignTeacherToClass($teacherId);

        $service = $this->app->make(TeacherClassScopeService::class);

        $allowed = $service->allowedStudentIds($teacherId, $this->subInstituteId);
        sort($allowed);
        $this->assertSame($this->sortedAssignedIds(), $allowed);

        $unassignedTeacherId = random_int(500000, 599999);
        $this->assertSame([], $service->allowedStudentIds($unassignedTeacherId, $this->subInstituteId));

        // Cross-tenant: the same teacher id/standard/division combination in
        // a DIFFERENT institute must not leak this institute's students.
        $this->assertSame([], $service->allowedStudentIds($teacherId, $this->subInstituteId + 1));
    }
}
