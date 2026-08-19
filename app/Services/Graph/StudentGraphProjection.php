<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns one student in MariaDB into outbox events for the graph.
 *
 * ---------------------------------------------------------------------------
 * THE SHAPE, AND WHERE IT COMES FROM
 * ---------------------------------------------------------------------------
 *   (:StuDetail {sdId})-[:HAS_STUDENT]->(:Student {stuId})-[:ENROLLED_IN]->(:Standard {stId})
 *
 *   :StuDetail = the PERSON.      sdId  = tblstudent.id
 *   :Student   = one ENROLLMENT.  stuId = tblstudent_enrollment.id
 *
 * This is not invented here — it is exactly what the k12 ingest script builds
 * (`MERGE (sd:StuDetail {sdId: toInteger(row.id)})` from tblstudent.csv,
 * `MERGE (stu:Student {stuId: toInteger(row.id)})` from
 * tblstudent_enrollment.csv), what `neo4j_sync_queue` already recorded 6,061
 * HAS_STUDENT + 6,053 ENROLLED_IN events for, and what `StudentGraphController`
 * reads back. Verified live 2026-08-17: 5,410 :Student, all keyed on `stuId`,
 * none carrying a `uid`.
 *
 * displayLabel formats match the ingest script exactly — "Student:{student_id}"
 * (the person id, not the enrollment id) and "Student Details:{first_name}".
 *
 * ---------------------------------------------------------------------------
 * WHAT IT DELIBERATELY DOES NOT WRITE
 * ---------------------------------------------------------------------------
 * `preferred_pedagogy`, `engagement_score` and `learning_velocity` live on
 * :Student but come from a separate PAL pass, not from tblstudent_enrollment
 * (which has no such columns). The node event carries only the columns this
 * projection owns, and the drain uses `SET n += $props`, so those PAL-authored
 * properties survive every re-sync instead of being blanked.
 *
 * NOTE FOR PHASE 7. `config/neo4j_graph.php` registers `tblstudent` as a
 * uid-keyed `:Student` and says "do NOT create :StuDetail (defect D5)". That is
 * the migration's TARGET, and it is a different grain from this one (person vs
 * enrollment). They cannot both be right — tracked as SYNC-SHAPE-DEBT. The
 * shape is defined only here, so re-pointing it is a one-file change.
 */
class StudentGraphProjection
{
    public const ENTITY = 'student';

    public function __construct(private readonly GraphOutbox $outbox)
    {
    }

    /**
     * Emit node + relationship events for a student and every enrollment they
     * hold. Call INSIDE the business transaction.
     *
     * Re-reads the authoritative rows rather than trusting the request payload,
     * so create and update are one code path and a replay always reflects what
     * was actually stored.
     *
     * @return array{log: int[], queue: int[]} ids written to each outbox table
     *
     * @throws RuntimeException when the student row does not exist
     */
    public function enqueue(int $studentId, int $tenantId): array
    {
        $student = DB::table('tblstudent')
            ->where('id', $studentId)
            ->where('sub_institute_id', $tenantId)
            ->first(['id', 'first_name', 'middle_name', 'last_name', 'email', 'mobile', 'admission_year']);

        if (! $student) {
            throw new RuntimeException("tblstudent row {$studentId} not found for tenant {$tenantId}");
        }

        $log = [];
        $queue = [];

        // ---- the person -----------------------------------------------------
        $log[] = $this->outbox->node('StuDetail', $studentId, array_filter([
            'sdId'             => $studentId,
            'student_id'       => $studentId,
            'first_name'       => $this->str($student->first_name),
            'middle_name'      => $this->str($student->middle_name),
            'last_name'        => $this->str($student->last_name),
            'email'            => $this->str($student->email),
            'mobile'           => $this->str($student->mobile),
            'admission_year'   => $this->intOrNull($student->admission_year),
            'sub_institute_id' => $tenantId,
            'displayLabel'     => 'Student Details:' . $this->str($student->first_name),
        ], fn ($v) => $v !== null));

        // ---- one :Student per enrollment ------------------------------------
        // All enrollments, not just the touched one: keeps the graph complete
        // and makes the projection idempotent and repair-capable.
        $enrollments = DB::table('tblstudent_enrollment')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $tenantId)
            ->get(['id', 'grade_id', 'standard_id', 'section_id', 'syear']);

        foreach ($enrollments as $e) {
            $enrollmentId = (int) $e->id;
            $standardId = $this->intOrNull($e->standard_id);

            $log[] = $this->outbox->node('Student', $enrollmentId, array_filter([
                'stuId'            => $enrollmentId,
                'student_id'       => $studentId,
                'grade_id'         => $this->intOrNull($e->grade_id),
                'standard_id'      => $standardId,
                'section_id'       => $this->intOrNull($e->section_id),
                'syear'            => $this->intOrNull($e->syear),
                'sub_institute_id' => $tenantId,
                'displayLabel'     => 'Student:' . $studentId,
            ], fn ($v) => $v !== null));

            // (:StuDetail)-[:HAS_STUDENT]->(:Student)
            $queue[] = $this->outbox->relationship(
                'StuDetail', $studentId, 'HAS_STUDENT', 'Student', $enrollmentId
            );

            // (:Student)-[:ENROLLED_IN]->(:Standard)
            // old_target_id lets the drain drop a stale enrolment when a
            // student changes class, instead of leaving them in both.
            if ($standardId !== null) {
                $queue[] = $this->outbox->relationship(
                    'Student', $enrollmentId, 'ENROLLED_IN', 'Standard', $standardId,
                    $this->currentStandardInGraph($enrollmentId, $standardId)
                );
            }
        }

        return ['log' => $log, 'queue' => $queue];
    }

    /**
     * The standard this enrollment was previously pointed at, if it differs
     * from the new one.
     *
     * Read from the LAST QUEUED EVENT rather than from Neo4j: this runs inside
     * the business transaction, where a bolt round-trip would put a remote
     * network call on the critical path of a database transaction — the
     * classic way to turn a graph outage into a table-level lock storm.
     */
    private function currentStandardInGraph(int $enrollmentId, int $newStandardId): ?int
    {
        $previous = DB::table('neo4j_sync_queue')
            ->where('source_table', 'Student')
            ->where('source_id', $enrollmentId)
            ->where('rel_type', 'ENROLLED_IN')
            ->orderByDesc('id')
            ->value('new_target_id');

        return ($previous !== null && (int) $previous !== $newStandardId)
            ? (int) $previous
            : null;
    }

    private function intOrNull($value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    private function str($value): string
    {
        return trim((string) ($value ?? ''));
    }
}
