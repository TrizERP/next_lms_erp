<?php

namespace App\Http\Controllers\api\OrganizationManagement\KnowledgeGraph;

use App\Domain\KnowledgeGraph\GraphModuleBrowser;
use App\Exceptions\GraphUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\Neo4jService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Organization Management -> Knowledge Graph.
 *
 * Reads the school's organization structure out of Neo4j and returns it as nodes and edges for
 * the picture on the Knowledge Graph tab:
 *
 *   Institute <-[:WORKS_AT]- Staff -[:HAS_ROLE]-> Role
 *   Staff -[:IN_DEPARTMENT]-> Department -[parent_id]-> Department
 *   Staff -[:TEACHES_SUBJECT]-> Subject   (optional, `subjects=1`)
 *
 * Read only, and always limited to the signed-in school (`sub_institute_id`). A school can have
 * hundreds of staff, so the picture shows a sample per role (`staff_per_role`, 5-100); the real
 * totals for every role and department come back as counts so the numbers stay honest.
 *
 * The queries themselves now live in `GraphModuleBrowser::organizationStaffGraph()`, reused
 * (not duplicated) by the rebuilt centralized Knowledge Graph explorer's own Organization
 * Management submodule — `index()` below calls that same method and reshapes its canonical
 * `{id, entityKey, recordId, label, source, attributes}` node / `{id, source, target, relation}`
 * edge shape back into this endpoint's own, unchanged `{id, type, label, count?, role?}` /
 * `{from, to, label}` response, so nothing calling this route sees any difference.
 */
class KnowledgeGraphController extends Controller
{
    public function __construct(
        private readonly Neo4jService $neo4j,
        private readonly GraphModuleBrowser $browser,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = (int) session()->get('sub_institute_id');
        if ($tenant <= 0) {
            return response()->json(['status' => 0, 'message' => 'No school selected for this session.'], 422);
        }

        $perRole = max(5, min(100, (int) $request->input('staff_per_role', 25)));
        $withSubjects = $request->boolean('subjects');

        try {
            $result = $this->browser->organizationStaffGraph($tenant, $perRole, $withSubjects);

            if (isset($result['error'])) {
                report(new \RuntimeException($result['error']));

                return response()->json(['status' => 0, 'message' => "Couldn't load the knowledge graph. Try again."], 500);
            }

            return response()->json(['status' => 1, 'data' => $this->toLegacyShape($result)]);
        } catch (GraphUnavailableException $e) {
            return response()->json(['status' => 0, 'message' => 'The knowledge graph database is not reachable right now.'], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => 0, 'message' => "Couldn't load the knowledge graph. Try again."], 500);
        }
    }

    /**
     * `GraphModuleBrowser::organizationStaffGraph()`'s canonical shape ->
     * this endpoint's original, unchanged response shape.
     *
     * @param array{nodes: list<array<string,mixed>>, edges: list<array<string,mixed>>, stats: array<string,int>} $result
     * @return array{nodes: list<array<string,mixed>>, edges: list<array<string,string>>, stats: array<string,int>}
     */
    private function toLegacyShape(array $result): array
    {
        $nodes = array_map(function (array $node): array {
            $legacy = [
                'id' => $node['id'],
                'type' => strtolower($node['entityKey']),
                'label' => $node['label'],
            ];
            if (array_key_exists('count', $node['attributes'] ?? [])) {
                $legacy['count'] = $node['attributes']['count'];
            }
            if (array_key_exists('role', $node['attributes'] ?? [])) {
                $legacy['role'] = $node['attributes']['role'];
            }

            return $legacy;
        }, $result['nodes']);

        $edges = array_map(fn (array $edge): array => [
            'from' => $edge['source'],
            'to' => $edge['target'],
            'label' => $edge['relation'],
        ], $result['edges']);

        return ['nodes' => $nodes, 'edges' => $edges, 'stats' => $result['stats']];
    }

    /**
     * One person's card for the details panel: a handful of safe profile fields from the staff
     * record, plus what the graph says about them (classes, subjects, leave types). Nothing
     * sensitive is returned: no login, bank or identity-document fields.
     */
    public function staff(Request $request, int $id): JsonResponse
    {
        $tenant = (int) session()->get('sub_institute_id');
        if ($tenant <= 0) {
            return response()->json(['status' => 0, 'message' => 'No school selected for this session.'], 422);
        }

        $user = DB::table('tbluser')->where('sub_institute_id', $tenant)->where('id', $id)->first([
            'id', 'first_name', 'middle_name', 'last_name', 'email', 'mobile', 'gender', 'city', 'qualification',
            'total_experience', 'employee_no', 'joined_date', 'status', 'last_login', 'department_id',
            'allocated_standards', 'user_profile_id', 'reporting_manager_id',
        ]);
        if ($user === null) {
            return response()->json(['status' => 0, 'message' => 'Staff member not found.'], 404);
        }

        $department = $user->department_id
            ? DB::table('hrms_departments')->where('sub_institute_id', $tenant)->where('id', $user->department_id)->value('department')
            : null;
        $jobRole = $user->allocated_standards && Schema::hasTable('s_user_jobrole')
            ? DB::table('s_user_jobrole')->where('id', (int) $user->allocated_standards)->value('jobrole')
            : null;
        $profile = $user->user_profile_id
            ? DB::table('tbluserprofilemaster')->where('sub_institute_id', $tenant)->where('id', $user->user_profile_id)->value('name')
            : null;
        $manager = $user->reporting_manager_id
            ? DB::table('tbluser')->where('sub_institute_id', $tenant)->where('id', $user->reporting_manager_id)
                ->selectRaw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS name")->value('name')
            : null;

        $facts = ['classTeacherOf' => [], 'subjects' => [], 'leaveTypes' => []];
        try {
            $params = ['t' => $tenant, 'sid' => (string) $id];
            foreach ($this->neo4j->run(
                'MATCH (s:Staff {sub_institute_id: $t}) WHERE toString(s.staffId) = $sid
                 OPTIONAL MATCH (s)-[:CLASS_TEACHER_OF]->(c:Division)
                 OPTIONAL MATCH (s)-[:TEACHES_SUBJECT]->(x:Subject)
                 OPTIONAL MATCH (s)-[:TOOK_LEAVE]->(l:LeaveType)
                 RETURN collect(DISTINCT coalesce(c.displayLabel, c.name)) AS classes,
                        collect(DISTINCT coalesce(x.displayLabel, x.name)) AS subjects,
                        collect(DISTINCT l.leave_type) AS leaves',
                $params
            ) as $row) {
                $clean = fn ($list) => array_values(array_filter(array_map('strval', (array) $list), fn ($v) => $v !== ''));
                $facts = ['classTeacherOf' => $clean($row->get('classes')), 'subjects' => $clean($row->get('subjects')), 'leaveTypes' => $clean($row->get('leaves'))];
            }
        } catch (Throwable $e) {
            // The profile card is still useful without the graph facts.
            report($e);
        }

        return response()->json(['status' => 1, 'data' => [
            'id' => (int) $user->id,
            'name' => trim(implode(' ', array_filter([$user->first_name, $user->middle_name, $user->last_name]))),
            'email' => $user->email,
            'mobile' => $user->mobile,
            'gender' => $user->gender,
            'city' => $user->city,
            'qualification' => $user->qualification,
            'experience' => $user->total_experience,
            'employeeNo' => $user->employee_no,
            'joined' => $user->joined_date,
            'active' => (int) $user->status === 1,
            'lastLogin' => $user->last_login,
            'department' => $department,
            'jobRole' => $jobRole,
            'profile' => $profile,
            'reportsTo' => $manager ?: null,
            'graph' => $facts,
        ]]);
    }

}
