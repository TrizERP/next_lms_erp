<?php

namespace App\Http\Controllers\api\OrganizationManagement\KnowledgeGraph;

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
 */
class KnowledgeGraphController extends Controller
{
    public function __construct(private readonly Neo4jService $neo4j)
    {
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
            return response()->json(['status' => 1, 'data' => $this->build($tenant, $perRole, $withSubjects)]);
        } catch (GraphUnavailableException $e) {
            return response()->json(['status' => 0, 'message' => 'The knowledge graph database is not reachable right now.'], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => 0, 'message' => "Couldn't load the knowledge graph. Try again."], 500);
        }
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

    /** @return array{nodes: list<array<string,mixed>>, edges: list<array<string,string>>, stats: array<string,int>} */
    private function build(int $tenant, int $perRole, bool $withSubjects): array
    {
        $nodes = [];
        $edges = [];
        $params = ['t' => $tenant];

        $institute = null;
        foreach ($this->neo4j->run('MATCH (i:Institute {sub_institute_id: $t}) RETURN i.SchoolName AS name LIMIT 1', $params) as $row) {
            $institute = (string) $row->get('name');
        }
        $nodes['institute'] = ['id' => 'institute', 'type' => 'institute', 'label' => $institute ?: 'School', 'count' => 0];

        $departments = [];
        foreach ($this->neo4j->run(
            'MATCH (d:Department {sub_institute_id: $t}) WHERE coalesce(d.deleted_at, "") = ""
             OPTIONAL MATCH (s:Staff)-[:IN_DEPARTMENT]->(d)
             RETURN d.id AS id, d.department AS name, d.parent_id AS parent, count(s) AS staff',
            $params
        ) as $row) {
            $id = 'dept-'.$row->get('id');
            $departments[$id] = (int) $row->get('parent');
            $nodes[$id] = ['id' => $id, 'type' => 'department', 'label' => (string) $row->get('name'), 'count' => (int) $row->get('staff')];
        }
        foreach ($departments as $id => $parent) {
            $parentId = $parent > 0 ? 'dept-'.$parent : null;
            $edges[] = ['from' => $parentId && isset($nodes[$parentId]) ? $parentId : 'institute', 'to' => $id, 'label' => 'HAS_DEPARTMENT'];
        }

        $totalStaff = 0;
        foreach ($this->neo4j->run(
            'MATCH (r:Role)<-[:HAS_ROLE]-(s:Staff {sub_institute_id: $t})
             RETURN r.id AS id, r.name AS name, count(DISTINCT s) AS staff ORDER BY staff DESC',
            $params
        ) as $row) {
            $roleId = 'role-'.$row->get('id');
            $total = (int) $row->get('staff');
            $totalStaff += $total;
            $nodes[$roleId] = ['id' => $roleId, 'type' => 'role', 'label' => (string) $row->get('name'), 'count' => $total];
            $edges[] = ['from' => 'institute', 'to' => $roleId, 'label' => 'HAS_ROLE'];

            foreach ($this->neo4j->run(
                'MATCH (r:Role {id: $rid})<-[:HAS_ROLE]-(s:Staff {sub_institute_id: $t})
                 OPTIONAL MATCH (s)-[:IN_DEPARTMENT]->(d:Department)
                 RETURN s.staffId AS id, s.first_name AS first, s.last_name AS last, collect(DISTINCT d.id) AS depts
                 ORDER BY first LIMIT $n',
                ['t' => $tenant, 'rid' => $row->get('id'), 'n' => $perRole]
            ) as $staff) {
                $staffId = 'staff-'.$staff->get('id');
                $nodes[$staffId] = [
                    'id' => $staffId, 'type' => 'staff', 'role' => $roleId,
                    'label' => trim($staff->get('first').' '.$staff->get('last')) ?: 'Staff',
                ];
                $edges[] = ['from' => $roleId, 'to' => $staffId, 'label' => 'HAS_ROLE'];
                foreach ($staff->get('depts') as $deptId) {
                    if (isset($nodes['dept-'.$deptId])) {
                        $edges[] = ['from' => 'dept-'.$deptId, 'to' => $staffId, 'label' => 'IN_DEPARTMENT'];
                    }
                }
            }
        }
        $nodes['institute']['count'] = $totalStaff;

        if ($withSubjects) {
            $shown = array_values(array_map(
                fn ($n) => substr($n['id'], 6),
                array_filter($nodes, fn ($n) => $n['type'] === 'staff')
            ));
            if ($shown !== []) {
                foreach ($this->neo4j->run(
                    'MATCH (s:Staff {sub_institute_id: $t})-[:TEACHES_SUBJECT]->(x:Subject) WHERE toString(s.staffId) IN $ids
                     RETURN toString(s.staffId) AS sid, x.id AS id, x.name AS name',
                    ['t' => $tenant, 'ids' => array_map('strval', $shown)]
                ) as $row) {
                    $subjectId = 'subject-'.$row->get('id');
                    $nodes[$subjectId] ??= ['id' => $subjectId, 'type' => 'subject', 'label' => (string) $row->get('name')];
                    $edges[] = ['from' => 'staff-'.$row->get('sid'), 'to' => $subjectId, 'label' => 'TEACHES_SUBJECT'];
                }
            }
        }

        $byType = array_count_values(array_column($nodes, 'type'));

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'stats' => [
                'staffTotal' => $totalStaff,
                'staffShown' => $byType['staff'] ?? 0,
                'roles' => $byType['role'] ?? 0,
                'departments' => $byType['department'] ?? 0,
                'subjects' => $byType['subject'] ?? 0,
                'edges' => count($edges),
            ],
        ];
    }
}
