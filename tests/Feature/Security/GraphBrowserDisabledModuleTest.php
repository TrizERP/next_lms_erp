<?php

namespace Tests\Feature\Security;

use App\Domain\KnowledgeGraph\GraphModuleBrowser;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Regression coverage for the gap found while wiring the Knowledge Graph
 * Settings toggle through to both graphs (2026-11-21): `search()`,
 * `nodeDetail()`, and `path()` on `GraphBrowserController` have no module or
 * submodule to anchor a check to (unlike `erpModuleGraph()`/
 * `erpSubmoduleGraph()`, which already filtered via `withoutDisabledNodeTypes()`),
 * so a node type an administrator switched off in
 * `/ai/knowledge-graph/settings` stayed fully searchable, expandable, and
 * path-findable through these three shared endpoints — which
 * ModuleKnowledgeGraph.tsx (the Decentralized per-module tab) calls as well,
 * not only the Centralized explorer.
 *
 * `GraphModuleBrowser` is mocked (same pattern as
 * `GraphBrowserTeacherScopingTest`): its own `erpModules()`/`erpSubmodules()`
 * are stubbed with a small fake tree so `GraphBrowserController::
 * globallyDisabledLabels()` has something deterministic to walk, without
 * depending on real `tblmenumaster` rows or live Neo4j.
 */
class GraphBrowserDisabledModuleTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;

    private const MODULE_LABEL = '__gbd_test_module__';

    private const SUBMODULE_STUDENT = '__gbd_test_submodule_student__';

    private const SUBMODULE_STANDARD = '__gbd_test_submodule_standard__';

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Graph Browser Disabled Module Test School',
            'ShortCode' => 'GBD' . random_int(1000, 9999),
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

    /**
     * One fake module with two submodules — Student mapped only under
     * SUBMODULE_STUDENT, Standard only under SUBMODULE_STANDARD — so
     * disabling one submodule's node type never touches the other's.
     */
    private function mockBrowserWithFakeTree(): GraphModuleBrowser
    {
        $mock = Mockery::mock(GraphModuleBrowser::class);
        $this->app->instance(GraphModuleBrowser::class, $mock);

        $mock->shouldReceive('erpModules')->andReturn([
            ['id' => 1, 'key' => '1', 'label' => self::MODULE_LABEL, 'submodule_count' => 2, 'label_count' => 2],
        ]);

        $mock->shouldReceive('erpSubmodules')->with(Mockery::any(), 1)->andReturn([
            ['id' => 11, 'key' => '11', 'label' => self::SUBMODULE_STUDENT, 'mapped' => true, 'labels' => ['Student'], 'node_count' => 1],
            ['id' => 12, 'key' => '12', 'label' => self::SUBMODULE_STANDARD, 'mapped' => true, 'labels' => ['Standard'], 'node_count' => 1],
        ]);

        return $mock;
    }

    private function disableStudentNodeType(): void
    {
        app(KnowledgeGraphSettingsResolver::class)->saveNodeTypeTree(
            self::MODULE_LABEL,
            self::SUBMODULE_STUDENT,
            'Student',
            $this->subInstituteId,
            false,
        );
    }

    // ------------------------------------------------------------------
    // search()
    // ------------------------------------------------------------------

    public function test_search_excludes_a_disabled_label_from_the_default_all_label_search(): void
    {
        $adminId = random_int(600000, 699999);
        $this->disableStudentNodeType();

        $this->mockBrowserWithFakeTree()
            ->shouldReceive('search')
            ->once()
            ->withArgs(function ($term, $tenantId, $limit, $labels) {
                return ! in_array('Student', $labels, true) && in_array('Standard', $labels, true);
            })
            ->andReturn(['term' => '', 'nodes' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson('/api/ai/knowledge-graph/browser/search')
            ->assertStatus(200);
    }

    public function test_search_with_only_disabled_labels_requested_returns_empty_without_calling_the_browser(): void
    {
        $adminId = random_int(600000, 699999);
        $this->disableStudentNodeType();

        $mock = $this->mockBrowserWithFakeTree();
        $mock->shouldNotReceive('search');

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson('/api/ai/knowledge-graph/browser/search?' . http_build_query(['labels' => ['Student']]))
            ->assertStatus(200)
            ->assertJsonPath('data.nodes', []);
    }

    public function test_search_is_unchanged_when_nothing_is_disabled(): void
    {
        $adminId = random_int(600000, 699999);
        // No saveNodeTypeTree() call at all for this institute.

        $this->mockBrowserWithFakeTree()
            ->shouldReceive('search')
            ->once()
            ->with('', $this->subInstituteId, 30, [], null)
            ->andReturn(['term' => '', 'nodes' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson('/api/ai/knowledge-graph/browser/search')
            ->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // nodeDetail()
    // ------------------------------------------------------------------

    public function test_node_detail_on_a_disabled_label_is_refused_without_calling_the_browser(): void
    {
        $adminId = random_int(600000, 699999);
        $this->disableStudentNodeType();

        $mock = $this->mockBrowserWithFakeTree();
        $mock->shouldNotReceive('nodeDetail');

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson('/api/ai/knowledge-graph/browser/node/Student/42')
            ->assertStatus(404);
    }

    public function test_node_detail_on_an_enabled_label_still_works(): void
    {
        $adminId = random_int(600000, 699999);
        $this->disableStudentNodeType();

        $this->mockBrowserWithFakeTree()
            ->shouldReceive('nodeDetail')
            ->once()
            ->with('Standard', '7', $this->subInstituteId, 50, 0, null, null, null)
            ->andReturn(['node' => null, 'nodes' => [], 'edges' => [], 'total_incoming' => 0, 'total_outgoing' => 0, 'has_more' => false]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson('/api/ai/knowledge-graph/browser/node/Standard/7')
            ->assertStatus(200);
    }

    public function test_node_detail_drops_a_disabled_neighbor_from_an_enabled_centers_relationships(): void
    {
        $adminId = random_int(600000, 699999);
        $this->disableStudentNodeType();

        $this->mockBrowserWithFakeTree()
            ->shouldReceive('nodeDetail')
            ->once()
            ->andReturn([
                'node' => ['id' => '100', 'entityKey' => 'Standard', 'recordId' => 7, 'label' => 'Standard #7', 'source' => 'graph', 'attributes' => []],
                'nodes' => [
                    ['id' => '100', 'entityKey' => 'Standard', 'recordId' => 7, 'label' => 'Standard #7', 'source' => 'graph', 'attributes' => []],
                    ['id' => '200', 'entityKey' => 'Student', 'recordId' => 42, 'label' => 'Student #42', 'source' => 'graph', 'attributes' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => '100', 'target' => '200', 'relation' => 'ENROLLED_IN'],
                ],
                'total_incoming' => 1,
                'total_outgoing' => 0,
                'has_more' => false,
            ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson('/api/ai/knowledge-graph/browser/node/Standard/7')
            ->assertStatus(200);

        $nodes = $response->json('data.nodes');
        $edges = $response->json('data.edges');

        $this->assertCount(1, $nodes);
        $this->assertSame('Standard', $nodes[0]['entityKey']);
        $this->assertSame([], $edges);
    }

    // ------------------------------------------------------------------
    // path()
    // ------------------------------------------------------------------

    public function test_path_through_a_disabled_endpoint_label_is_reported_as_not_found_without_calling_the_browser(): void
    {
        $adminId = random_int(600000, 699999);
        $this->disableStudentNodeType();

        $mock = $this->mockBrowserWithFakeTree();
        $mock->shouldNotReceive('findPath');

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->postJson('/api/ai/knowledge-graph/browser/path', [
                'from_label' => 'Student', 'from_id' => 1,
                'to_label' => 'Standard', 'to_id' => 2,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.nodes', []);
    }

    public function test_path_between_two_enabled_labels_still_works(): void
    {
        $adminId = random_int(600000, 699999);
        $this->disableStudentNodeType();

        $this->mockBrowserWithFakeTree()
            ->shouldReceive('findPath')
            ->once()
            ->with('Standard', '2', 'Standard', '3', $this->subInstituteId, 6, [], null)
            ->andReturn(['found' => false, 'nodes' => [], 'edges' => []]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->postJson('/api/ai/knowledge-graph/browser/path', [
                'from_label' => 'Standard', 'from_id' => 2,
                'to_label' => 'Standard', 'to_id' => 3,
            ])
            ->assertStatus(200);
    }
}
