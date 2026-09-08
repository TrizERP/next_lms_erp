<?php

namespace Tests\Feature\Brain;

use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The graph has to be walkable, and every node it offers has to open.
 *
 * A graph screen fails quietly: it renders, the nodes look plausible, and only
 * when someone clicks one does it turn out the expansion was never wired or
 * points at a row in another institute. So this test walks it the way a person
 * would — roots, then a node of each type, then that node's own neighbours —
 * and checks each hop against the LMS rows it claims to describe.
 */
class BrainGraphWalkTest extends TestCase
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

        // An institute whose staff sit in departments IT OWNS. That join matters:
        // tbluser.department_id is not tenant-scoped in this database, so plenty
        // of institutes have staff pointing at another institute's departments
        // and would give this test nothing to walk.
        $row = DB::table('tbluser')
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->join('hrms_departments as d', 'd.id', '=', 'tbluser.department_id')
            ->whereColumn('d.sub_institute_id', 'tbluser.sub_institute_id')
            ->where('d.status', 1)
            ->groupBy('tbluser.sub_institute_id')
            ->orderByRaw('COUNT(*) DESC')
            ->first(['tbluser.sub_institute_id']);

        if (! $row) {
            $this->markTestSkipped('No institute has staff posted to a department it owns.');
        }

        $this->tenantId = (string) $row->sub_institute_id;

        $user = DB::table('tbluser')
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->where('tbluser.sub_institute_id', $this->tenantId)
            ->first(['tbluser.id', 'tbluser.user_profile_id']);

        $this->token = (string) app(JwtToken::class)->createToken([
            'id' => $user->id,
            'sub_institute_id' => $this->tenantId,
            'is_admin' => 1,
            'client_id' => null,
            'user_profile_id' => $user->user_profile_id,
            'is_student' => false,
        ]);
    }

    private function graph(array $params = [])
    {
        $query = $params ? '?'.http_build_query($params) : '';

        return $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/brain/'.$this->tenantId.'/graph'.$query)
            ->assertOk()
            ->json();
    }

    public function test_the_organization_root_expands_into_real_edges(): void
    {
        $payload = $this->graph();

        $this->assertTrue($payload['available'], 'The graph reports itself unavailable for an institute with staff.');
        $this->assertNotEmpty($payload['roots']);
        $this->assertNotEmpty($payload['organization']['edges'] ?? [], 'The organization node has no edges.');

        foreach ($payload['organization']['edges'] as $edge) {
            $this->assertNotEmpty($edge['label']);
            // An edge that claims neighbours must actually carry some.
            if ((int) $edge['total'] > 0) {
                $this->assertNotEmpty($edge['nodes'], $edge['label'].' claims '.$edge['total'].' neighbours but returned none.');
            }
        }
    }

    /** Every node type the roots advertise must list and then expand. */
    public function test_every_offered_node_type_lists_and_expands(): void
    {
        foreach ($this->graph()['roots'] as $root) {
            if ((int) $root['count'] === 0) {
                continue;
            }

            $listing = $this->graph(['type' => $root['type']]);
            $nodes = $listing['nodes'] ?? [];

            // Organization is the root itself and has no separate listing.
            if ($root['type'] === 'organization' || $nodes === []) {
                continue;
            }

            $node = $nodes[0];
            $this->assertNotEmpty($node['label'], $root['type'].' node has no label');
            $this->assertNotSame($node['id'], $node['label'], $root['type'].' node is labelled with its id');

            $expansion = $this->graph(['type' => $root['type'], 'id' => $node['id']]);
            $this->assertTrue(
                (bool) $expansion['available'],
                sprintf('%s node "%s" would not expand: %s', $root['type'], $node['label'], $expansion['reason'] ?? '')
            );
            $this->assertSame($node['id'], $expansion['node']['id']);
        }
    }

    public function test_a_department_expansion_lists_that_departments_own_staff(): void
    {
        // BOTH sides scoped. Scoping only the department picks one whose staff
        // all belong to a different institute — the very confusion this suite
        // exists to catch.
        $department = DB::table('hrms_departments as d')
            ->join('tbluser as u', 'u.department_id', '=', 'd.id')
            ->join('tbluserprofilemaster as p', 'u.user_profile_id', '=', 'p.id')
            ->where('d.sub_institute_id', $this->tenantId)
            ->where('u.sub_institute_id', $this->tenantId)
            ->where('d.status', 1)
            ->groupBy('d.id')->orderByRaw('COUNT(*) DESC')
            ->first(['d.id']);

        if (! $department) {
            $this->markTestSkipped('No department in this institute holds staff.');
        }

        $expected = DB::table('tbluser')
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->where('tbluser.sub_institute_id', $this->tenantId)
            ->where('tbluser.department_id', $department->id)
            ->pluck('tbluser.id')->map(fn ($id) => (string) $id)->all();

        $expansion = $this->graph(['type' => 'department', 'id' => (string) $department->id]);
        $staffEdge = collect($expansion['edges'])->firstWhere('label', 'Staff in this department');

        $this->assertNotNull($staffEdge, 'A department with staff has no Staff edge.');
        $this->assertSame(count($expected), (int) $staffEdge['total'], 'Department headcount');

        foreach ($staffEdge['nodes'] as $person) {
            $this->assertContains(
                $person['id'],
                $expected,
                'The graph put '.$person['label'].' in a department they do not belong to.'
            );
        }
    }

    public function test_a_node_from_another_institute_is_not_expandable(): void
    {
        $foreign = DB::table('hrms_departments')
            ->where('sub_institute_id', '!=', $this->tenantId)
            ->value('id');

        if ($foreign === null) {
            $this->markTestSkipped('Only one institute in this database.');
        }

        $expansion = $this->graph(['type' => 'department', 'id' => (string) $foreign]);
        $this->assertFalse((bool) $expansion['available'], 'Another institute\'s department expanded.');
    }
}
