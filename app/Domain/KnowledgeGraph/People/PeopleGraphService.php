<?php

namespace App\Domain\KnowledgeGraph\People;

use App\Services\Neo4jService;

/**
 * Read-only Neo4j queries for the Users and Attendance agents.
 *
 * Every query is bound to `$inst` on EVERY node it touches (not only the first), so a
 * traversal can never wander into another institute's graph. Nothing here writes to Neo4j.
 *
 * The relationships used already exist in the graph (loaded by the Neo4j migration):
 *
 *   (Staff)-[:HAS_ROLE]->(Role)               tbluser.user_profile_id → tbluserprofilemaster
 *   (Staff)-[:WORKS_AT]->(Institute)          employment at the institute
 *   (Staff)-[:IN_DEPARTMENT]->(Department)
 *   (Staff)-[:CLASS_TEACHER_OF {syear}]->(Division)
 *   (Staff)-[:TEACHES_CLASS {syear,periods}]->(Division)     from the timetable
 *   (Student)-[:IN_DIVISION]->(Division), (Student {standard_id, section_id, syear})
 *   (StuDetail)-[:ATTENDANCE {term_id,percentage,present,working_days}]->(AcademicYear)
 *
 * Two honest limits, surfaced to the agents rather than hidden:
 *   · `Division` nodes are the division NAME list (A, B, …), shared by every standard, so
 *     CLASS_TEACHER_OF says "teacher of division A" without saying which standard.
 *   · The graph's `ATTENDANCE` edge is the per-term attendance summary, not daily marks.
 */
class PeopleGraphService
{
    public function __construct(private readonly Neo4jService $neo4j)
    {
    }

    /** Round-trip check. @return array{ok: bool, ms: int, error: ?string} */
    public function ping(): array
    {
        $t = microtime(true);
        try {
            $this->neo4j->run('RETURN 1 AS ok');

            return ['ok' => true, 'ms' => (int) round((microtime(true) - $t) * 1000), 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ms' => (int) round((microtime(true) - $t) * 1000), 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /**
     * Where the graph places each student. The graph holds one `Student` node per student per
     * academic year, so pass the year the comparison is for; with null the newest year wins.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array<string, mixed>> student_id => placement
     */
    public function studentPlacement(int $inst, array $studentIds, ?int $syear = null): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = $this->rows(
            'MATCH (s:Student) WHERE s.sub_institute_id = $inst AND s.student_id IN $ids AND ($syear IS NULL OR s.syear = $syear)
             OPTIONAL MATCH (s)-[:IN_DIVISION]->(d:Division) WHERE d.sub_institute_id = $inst
             RETURN s.student_id AS student_id, s.standard_id AS standard_id, s.section_id AS section_id,
                    s.syear AS syear, s.sub_institute_id AS inst, collect(DISTINCT d.id) AS divisions',
            ['inst' => $inst, 'ids' => array_values(array_map('intval', $studentIds)), 'syear' => $syear]
        );

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['student_id'];
            // Several Student nodes can exist per person (one per year): keep the newest.
            if (! isset($out[$id]) || (int) $r['syear'] > (int) $out[$id]['syear']) {
                $out[$id] = [
                    'standard_id' => $r['standard_id'] === null ? null : (int) $r['standard_id'],
                    'section_id' => $r['section_id'] === null ? null : (int) $r['section_id'],
                    'syear' => (int) $r['syear'],
                    'inst' => (int) $r['inst'],
                    'divisions' => array_map('strval', (array) $r['divisions']),
                ];
            }
        }

        return $out;
    }

    /**
     * Class teachers by division id.
     *
     * @param  array<int, int|string>  $divisionIds
     * @return array<string, array<int, array<string, mixed>>> division id => staff
     */
    public function classTeachersByDivision(int $inst, array $divisionIds): array
    {
        if ($divisionIds === []) {
            return [];
        }

        $rows = $this->rows(
            'MATCH (st:Staff)-[r:CLASS_TEACHER_OF]->(d:Division)
             WHERE st.sub_institute_id = $inst AND d.sub_institute_id = $inst AND d.id IN $divs
             RETURN d.id AS division_id, st.staffId AS staff_id, st.first_name AS first_name,
                    st.last_name AS last_name, r.syear AS syear',
            ['inst' => $inst, 'divs' => array_values(array_map('strval', $divisionIds))]
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['division_id']][] = [
                'staff_id' => (int) $r['staff_id'],
                'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                'syear' => $r['syear'] === null ? null : (int) $r['syear'],
            ];
        }

        return $out;
    }

    /**
     * The per-term attendance summary the graph holds for each student.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array<int, array<string, mixed>>> student_id => terms (newest first)
     */
    public function termAttendance(int $inst, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = $this->rows(
            'MATCH (sd:StuDetail)-[r:ATTENDANCE]->(y:AcademicYear)
             WHERE sd.sub_institute_id = $inst AND sd.student_id IN $ids
             RETURN sd.student_id AS student_id, r.term_id AS term_id, r.syear AS syear,
                    r.percentage AS percentage, r.present AS present, r.working_days AS working_days',
            ['inst' => $inst, 'ids' => array_values(array_map('intval', $studentIds))]
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['student_id']][] = [
                'term_id' => $r['term_id'] === null ? null : (int) $r['term_id'],
                'syear' => $r['syear'] === null ? null : (int) $r['syear'],
                'percentage' => $r['percentage'] === null ? null : (float) $r['percentage'],
                'present' => $r['present'] === null ? null : (int) $r['present'],
            ];
        }
        foreach ($out as &$terms) {
            usort($terms, static fn ($a, $b) => [$b['syear'], $b['term_id']] <=> [$a['syear'], $a['term_id']]);
        }

        return $out;
    }

    /**
     * Every Staff node of the institute with its roles and org links.
     *
     * @return array<int, array<string, mixed>> staff id (tbluser.id) => facts
     */
    public function staffMap(int $inst): array
    {
        $rows = $this->rows(
            'MATCH (s:Staff) WHERE s.sub_institute_id = $inst
             RETURN s.staffId AS id, s.user_profile_id AS profile_id, s.department_id AS department_id, s.status AS status,
                    [(s)-[:HAS_ROLE]->(r:Role) WHERE r.sub_institute_id = $inst | r.id] AS roles,
                    size([(s)-[:WORKS_AT]->(i:Institute) WHERE i.sub_institute_id = $inst | 1]) AS works_at,
                    size([(s)-[:IN_DEPARTMENT]->(d:Department) WHERE d.sub_institute_id = $inst | 1]) AS in_department,
                    size([(s)-[:CLASS_TEACHER_OF]->(d:Division) WHERE d.sub_institute_id = $inst | 1]) AS class_teacher_of,
                    size([(s)-[:TEACHES_CLASS]->(d:Division) WHERE d.sub_institute_id = $inst | 1]) AS teaches_class',
            ['inst' => $inst]
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = [
                'profile_id' => $r['profile_id'] === null ? null : (int) $r['profile_id'],
                'department_id' => $r['department_id'] === null ? null : (int) $r['department_id'],
                'status' => (string) $r['status'],
                'role_ids' => array_map('intval', (array) $r['roles']),
                'works_at' => (int) $r['works_at'],
                'in_department' => (int) $r['in_department'],
                'class_teacher_of' => (int) $r['class_teacher_of'],
                'teaches_class' => (int) $r['teaches_class'],
            ];
        }

        return $out;
    }

    /** Role nodes of the institute. @return array<int, string> role id => name */
    public function roles(int $inst): array
    {
        $out = [];
        foreach ($this->rows('MATCH (r:Role) WHERE r.sub_institute_id = $inst RETURN r.id AS id, r.name AS name', ['inst' => $inst]) as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }

        return $out;
    }

    /**
     * Class-teacher links as the graph holds them, for one academic year.
     *
     * @return array<int, array<string, mixed>> rows of staff_id, division_id
     */
    public function classTeacherLinks(int $inst, ?int $syear): array
    {
        return array_map(static fn (array $r) => [
            'staff_id' => (int) $r['staff_id'],
            'division_id' => (string) $r['division_id'],
            'syear' => $r['syear'] === null ? null : (int) $r['syear'],
        ], $this->rows(
            'MATCH (st:Staff)-[r:CLASS_TEACHER_OF]->(d:Division)
             WHERE st.sub_institute_id = $inst AND d.sub_institute_id = $inst AND ($syear IS NULL OR r.syear = $syear)
             RETURN st.staffId AS staff_id, d.id AS division_id, r.syear AS syear',
            ['inst' => $inst, 'syear' => $syear]
        ));
    }

    // ───────────────────────── internals ─────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function rows(string $cypher, array $params): array
    {
        $out = [];
        foreach ($this->neo4j->run($cypher, $params) as $row) {
            $out[] = $this->plain($row->toArray());
        }

        return $out;
    }

    private function plain(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->plain($v), $value);
        }

        return $value;
    }
}
