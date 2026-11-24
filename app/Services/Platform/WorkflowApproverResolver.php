<?php

namespace App\Services\Platform;

use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Resolves `role|principal|class_teacher|reporting_manager` approver types to
 * a real `tbluser.id`, using the organization graph that is already live in
 * Neo4j (`StaffGraphProjection`'s `REPORTS_TO`/`HAS_ROLE`, and
 * `class_teacher`'s `CLASS_TEACHER_OF`).
 *
 * `WorkflowEngine` previously left these four types entirely unresolved —
 * see its own "ANYONE-WITH-RIGHTS RULE" docblock — and relied only on the
 * route's permission gate. This class closes that for the three cases the
 * graph can actually answer. `role` stays unresolved on purpose: a role name
 * does not identify one person, and "anyone holding this role" is the
 * intended behaviour for that type, not a gap.
 *
 * Returns null, never a guess, when the graph has no real answer — an
 * unresolved step falls back to exactly the pre-existing permission-gate
 * behaviour.
 */
class WorkflowApproverResolver
{
    public function __construct(private readonly ?Neo4jService $neo4j = null)
    {
    }

    /**
     * @param  string  $approverType  'principal'|'class_teacher'|'reporting_manager' (others return null)
     * @param  array{requested_by?: int|null, student_id?: int|null, division_id?: int|null}  $context
     */
    public function resolve(int $tenantId, string $approverType, array $context = []): ?int
    {
        if ($this->neo4j === null) {
            return null;
        }

        return match ($approverType) {
            'reporting_manager' => $this->resolveReportingManager($tenantId, $context['requested_by'] ?? null),
            'principal' => $this->resolveRole($tenantId, 'Principal'),
            'class_teacher' => $this->resolveClassTeacher($tenantId, $context),
            default => null,
        };
    }

    /** The real person's REPORTS_TO target — "the requester's reporting manager". */
    private function resolveReportingManager(int $tenantId, ?int $staffId): ?int
    {
        if ($staffId === null) {
            return null;
        }

        return $this->firstStaffId(
            'MATCH (s:Staff {staffId: $staffId})-[:REPORTS_TO]->(m:Staff) '
            . 'WHERE (m.sub_institute_id IS NULL OR m.sub_institute_id = $tenantId) '
            . 'RETURN m.staffId AS staffId LIMIT 1',
            ['staffId' => $staffId, 'tenantId' => $tenantId]
        );
    }

    /** Whoever holds a named Role at this tenant — same shape as GraphModuleBrowser::organizationStaffGraph(). */
    private function resolveRole(int $tenantId, string $roleName): ?int
    {
        return $this->firstStaffId(
            'MATCH (r:Role) WHERE toLower(r.name) = toLower($roleName) '
            . 'MATCH (r)<-[:HAS_ROLE]-(s:Staff {sub_institute_id: $tenantId}) '
            . 'RETURN s.staffId AS staffId LIMIT 1',
            ['roleName' => $roleName, 'tenantId' => $tenantId]
        );
    }

    /**
     * "The class teacher of the student on the record" — only resolvable
     * when the caller's context actually names a student or division; no
     * generic entity_type -> student bridge exists to guess one otherwise.
     */
    private function resolveClassTeacher(int $tenantId, array $context): ?int
    {
        $divisionId = $context['division_id'] ?? null;

        if ($divisionId === null && isset($context['student_id'])) {
            $divisionId = DB::table('tblstudent_enrollment')
                ->where('student_id', $context['student_id'])
                ->where('sub_institute_id', $tenantId)
                ->orderByDesc('id')
                ->value('division_id');
        }

        if (! is_numeric($divisionId)) {
            return null;
        }

        return $this->firstStaffId(
            'MATCH (d:Division {divisionId: $divisionId}) '
            . 'MATCH (s:Staff)-[:CLASS_TEACHER_OF]->(d) '
            . 'WHERE (s.sub_institute_id IS NULL OR s.sub_institute_id = $tenantId) '
            . 'RETURN s.staffId AS staffId LIMIT 1',
            ['divisionId' => (int) $divisionId, 'tenantId' => $tenantId]
        );
    }

    private function firstStaffId(string $cypher, array $params): ?int
    {
        try {
            $result = $this->neo4j->run($cypher, $params);
        } catch (Throwable) {
            return null;
        }

        foreach ($result ?? [] as $row) {
            $staffId = $row->get('staffId');

            return is_numeric($staffId) ? (int) $staffId : null;
        }

        return null;
    }
}
