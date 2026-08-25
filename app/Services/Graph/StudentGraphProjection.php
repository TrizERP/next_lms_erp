<?php

namespace App\Services\Graph;

use App\Services\Graph\Contracts\GraphProjection;
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
 * reads back. Re-verified live 2026-08-21: 5,418 :Student / 5,418 HAS_STUDENT /
 * 5,357 ENROLLED_IN, all keyed on `stuId`, none carrying a `uid`.
 *
 * displayLabel formats match the ingest script exactly — "Student:{student_id}"
 * (the person id, not the enrollment id) and "Student Details:{first_name}".
 *
 * ---------------------------------------------------------------------------
 * TWO TABLES, ONE SUBGRAPH
 * ---------------------------------------------------------------------------
 * A change to `tblstudent` OR to `tblstudent_enrollment` re-projects the whole
 * person: their :StuDetail plus every :Student they hold. Re-projecting the lot
 * is what makes the projection idempotent and self-repairing — an enrolment
 * added by a path nobody remembers still lands correctly, because the next
 * event of any kind rebuilds the person's entire subgraph.
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
class StudentGraphProjection implements GraphProjection
{
    public const ENTITY = 'student';

    public function __construct(private readonly GraphOutbox $outbox)
    {
    }

    public function tables(): array
    {
        return ['tblstudent', 'tblstudent_enrollment'];
    }

    public function labels(): array
    {
        return ['StuDetail', 'Student'];
    }

    /**
     * Emit node + relationship events for a student and every enrollment they
     * hold.
     *
     * Re-reads the authoritative rows rather than trusting the request payload
     * or the trigger's captured columns, so create and update are one code path
     * and a replay always reflects what was actually stored.
     *
     * @param  string  $table     'tblstudent' (recordId = person) or
     *                            'tblstudent_enrollment' (recordId = enrolment)
     * @param  array   $hints     trigger-captured columns; `student_id` and
     *                            `sub_institute_id` are used when present
     * @return array{log: int[], queue: int[]} ids written to each outbox table
     *
     * @throws RuntimeException when the student row does not exist
     */
    public function enqueue(string $table, int $recordId, array $hints = []): array
    {
        $studentId = $this->resolveStudentId($table, $recordId, $hints);

        $student = DB::table('tblstudent')
            ->where('id', $studentId)
            ->first(['id', 'first_name', 'middle_name', 'last_name', 'email', 'mobile', 'admission_year', 'sub_institute_id']);

        if (! $student) {
            throw new RuntimeException("tblstudent row {$studentId} not found");
        }

        $tenantId = (int) ($student->sub_institute_id ?: ($hints['sub_institute_id'] ?? 0));

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
            ->get(['id', 'grade_id', 'standard_id', 'section_id', 'syear', 'sub_institute_id']);

        foreach ($enrollments as $e) {
            $enrollmentId = (int) $e->id;
            $standardId = $this->intOrNull($e->standard_id);
            $enrollmentTenant = (int) ($e->sub_institute_id ?: $tenantId);

            $log[] = $this->outbox->node('Student', $enrollmentId, array_filter([
                'stuId'            => $enrollmentId,
                'student_id'       => $studentId,
                'grade_id'         => $this->intOrNull($e->grade_id),
                'standard_id'      => $standardId,
                'section_id'       => $this->intOrNull($e->section_id),
                'syear'            => $this->intOrNull($e->syear),
                'sub_institute_id' => $enrollmentTenant,
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
     * A student or one of their enrolments was deleted.
     *
     * `tblstudent_enrollment` is the easy case: drop that one :Student and let
     * DETACH DELETE take its edges.
     *
     * `tblstudent` is not, because the row — and under an ON DELETE CASCADE
     * every enrolment row with it — is already gone, and InnoDB does not fire
     * triggers for cascaded deletes. The enrolment ids are recovered from
     * `neo4j_sync_queue`, which recorded a HAS_STUDENT edge for every :Student
     * this person ever had. A person deleted before they ever synced leaves no
     * history, but also left nothing in the graph to clean up.
     */
    public function delete(string $table, int $recordId, array $hints = []): array
    {
        if ($table === 'tblstudent_enrollment') {
            $studentId = $this->intOrNull($hints['student_id'] ?? null);

            // The person survives — re-project them so the remaining
            // enrolments stay correct, then drop the departed one.
            $ids = ($studentId !== null && $this->personExists($studentId))
                ? $this->enqueue('tblstudent', $studentId, $hints)
                : ['log' => [], 'queue' => []];

            $ids['log'][] = $this->outbox->node('Student', $recordId, [], 'DELETE');

            return $ids;
        }

        $log = [$this->outbox->node('StuDetail', $recordId, [], 'DELETE')];

        foreach ($this->enrollmentsEverSynced($recordId) as $enrollmentId) {
            $log[] = $this->outbox->node('Student', $enrollmentId, [], 'DELETE');
        }

        return ['log' => $log, 'queue' => []];
    }

    /**
     * Both tables describe one person, so both collapse onto that person.
     */
    public function entityKey(string $table, int $recordId, array $hints = []): string
    {
        return 'student:' . $this->resolveStudentId($table, $recordId, $hints);
    }

    /**
     * :StuDetail is keyed by person, :Student by enrolment — each maps onto the
     * table that carries that grain, and either way the whole person is
     * rebuilt.
     */
    public function enqueueNode(string $label, int $nodeId): array
    {
        $table = match ($label) {
            'StuDetail' => 'tblstudent',
            'Student'   => 'tblstudent_enrollment',
            default     => null,
        };

        if ($table === null || ! DB::table($table)->where('id', $nodeId)->exists()) {
            return ['log' => [], 'queue' => []];
        }

        return $this->enqueue($table, $nodeId);
    }

    // -----------------------------------------------------------------------

    /**
     * Which person does this event concern?
     *
     * An enrolment event carries the person in `student_id` — from the trigger
     * for a live change, or read back from the row when the event came from
     * somewhere else (backfill, reconcile).
     */
    private function resolveStudentId(string $table, int $recordId, array $hints): int
    {
        if ($table === 'tblstudent') {
            return $recordId;
        }

        if ($table !== 'tblstudent_enrollment') {
            throw new RuntimeException("StudentGraphProjection does not own table '{$table}'");
        }

        $studentId = $this->intOrNull($hints['student_id'] ?? null)
            ?? $this->intOrNull(DB::table('tblstudent_enrollment')->where('id', $recordId)->value('student_id'));

        if ($studentId === null) {
            throw new RuntimeException("tblstudent_enrollment row {$recordId} has no resolvable student_id");
        }

        return $studentId;
    }

    private function personExists(int $studentId): bool
    {
        return DB::table('tblstudent')->where('id', $studentId)->exists();
    }

    /**
     * Every enrolment id this person was ever given a HAS_STUDENT edge for.
     *
     * @return int[]
     */
    private function enrollmentsEverSynced(int $studentId): array
    {
        return DB::table('neo4j_sync_queue')
            ->where('source_table', 'StuDetail')
            ->where('source_id', $studentId)
            ->where('rel_type', 'HAS_STUDENT')
            ->whereNotNull('new_target_id')
            ->distinct()
            ->pluck('new_target_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The standard this enrollment was previously pointed at, if it differs
     * from the new one.
     *
     * Read from the LAST QUEUED EVENT rather than from Neo4j: this can run
     * inside a business transaction, where a bolt round-trip would put a remote
     * network call on the critical path of a database transaction — the classic
     * way to turn a graph outage into a table-level lock storm.
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
