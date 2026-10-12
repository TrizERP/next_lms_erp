<?php

namespace App\Services\Graph;

use App\Services\Graph\Contracts\GraphProjection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns one `lms_student_engagement` row into a (:StuDetail)-[:ENGAGED_WITH]->
 * (:Concept|:TeachingResource) edge.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS BESPOKE AND NOT A `config/neo4j.php` COLUMN-MAP ENTITY
 * ---------------------------------------------------------------------------
 * `TableGraphProjection` MERGEs one row onto one node (or one declared edge)
 * whose target LABEL is fixed in the spec. This table's `activity_id` column
 * means something different depending on `activity_type` — for 'content_view'
 * it is a `content_master.id` (:TeachingResource); for 'practice'/'quiz' it is
 * a `lms_concept.id` (:Concept); for 'homework_submit' it is the
 * `homework.id` row itself, which has no concept/content FK at all and
 * therefore produces no edge. A declarative spec cannot pick a target label
 * per-row, so this is handled in PHP instead — the same reason
 * `StudentGraphProjection`/`ResultGraphProjection`/`StaffGraphProjection` are
 * bespoke.
 *
 * ---------------------------------------------------------------------------
 * WHY NO NEW NODE LABEL
 * ---------------------------------------------------------------------------
 * The task is "student -> engagement events -> concept/content links", i.e.
 * the edge IS the fact worth projecting; an engagement event is not itself a
 * reusable graph entity anything else points at. `labels()` returns `[]` on
 * purpose — this projection never MERGEs a node, only edges between nodes two
 * OTHER projections already own (:StuDetail via `StudentGraphProjection`,
 * :Concept/:TeachingResource via `TableGraphProjection`).
 *
 * ---------------------------------------------------------------------------
 * WHY :StuDetail, NOT :Student
 * ---------------------------------------------------------------------------
 * `lms_student_engagement.student_id` is `tblstudent.id` (the PERSON), exactly
 * like `lms_offline_exam.student_id` / `lms_online_exam_student.student_id`
 * already key their `StuDetail` endpoint in `config/neo4j.php` — confirmed
 * here the same way: `ApiLoginController` resolves a student session's
 * `user_id` from `tblstudent.id`, and every one of the three real call sites
 * that write this table (`palController::incrementContentVisit`,
 * `assessmentQuestionController::submitPractice`,
 * `HomeworkSubmissionApiController::submit`) reads `student_id` from that same
 * session value.
 *
 * ---------------------------------------------------------------------------
 * WHY THE EDGE CARRIES NO PROPERTIES
 * ---------------------------------------------------------------------------
 * Bare MERGE, matching the documented precedent for `lms_question_master`'s
 * ASSESSES edge in `config/neo4j.php` (no `properties` entry, specifically so
 * two independent writers can never fight over a property on the same edge
 * type). Many engagement rows between the same student and the same
 * concept/content collapse onto one edge — "has engaged with", not "engaged
 * this many times" — which is the aggregate fact a graph traversal needs; the
 * per-event detail (time spent, count, when) stays in MariaDB, which is what
 * the read API (`EngagementReportService`) actually serves.
 */
class EngagementGraphProjection implements GraphProjection
{
    public const ENTITY = 'engagement';

    private const TABLE = 'lms_student_engagement';

    public function __construct(private readonly GraphOutbox $outbox)
    {
    }

    public function tables(): array
    {
        return [self::TABLE];
    }

    /** Owns no node label — see the class docblock. */
    public function labels(): array
    {
        return [];
    }

    public function enqueue(string $table, int $recordId, array $hints = []): array
    {
        $row = DB::table(self::TABLE)->where('id', $recordId)->first();

        if (! $row) {
            throw new RuntimeException(self::TABLE . " row {$recordId} not found");
        }

        $studentId = $this->intOrNull($row->student_id);

        if ($studentId === null || ! $this->studentExists($studentId)) {
            // No resolvable person, or the person has since been removed —
            // nothing to hang the edge off.
            return ['log' => [], 'queue' => []];
        }

        $target = $this->resolveTarget((string) $row->activity_type, $this->intOrNull($row->activity_id));

        if ($target === null) {
            // 'homework_submit' and any other activity_type with no
            // concept/content FK: the row is still a real engagement event
            // (readable via EngagementReportService) but it has nothing a
            // graph edge can point at. Not an error — the same "say so
            // plainly" rule content_master.concept_id's 1/30,792 rows follow.
            return ['log' => [], 'queue' => []];
        }

        [$targetLabel, $targetId] = $target;

        $queue = [$this->outbox->relationship('StuDetail', $studentId, 'ENGAGED_WITH', $targetLabel, $targetId)];

        return ['log' => [], 'queue' => $queue];
    }

    /**
     * The row is gone. Rebuild the edge identity from the trigger hints and
     * drop the edge ONLY when no other engagement row still justifies it —
     * mirroring `TableGraphProjection::delete()`'s survivor check for a
     * `key_column` entity, because several engagement rows legitimately
     * collapse onto the same bare edge.
     */
    public function delete(string $table, int $recordId, array $hints = []): array
    {
        $studentId = $this->intOrNull($hints['student_id'] ?? null);
        $target = $studentId === null
            ? null
            : $this->resolveTarget((string) ($hints['activity_type'] ?? ''), $this->intOrNull($hints['activity_id'] ?? null));

        if ($studentId === null || $target === null) {
            return ['log' => [], 'queue' => []];
        }

        [$targetLabel, $targetId] = $target;

        if ($this->survivingEngagementExists($studentId, $targetLabel, $targetId, $recordId)) {
            return ['log' => [], 'queue' => []];
        }

        $queue = [$this->outbox->relationship(
            'StuDetail', $studentId, 'ENGAGED_WITH', $targetLabel, null, $targetId, 'DELETE'
        )];

        return ['log' => [], 'queue' => $queue];
    }

    /** Each engagement row is its own event — nothing to collapse onto a shared key. */
    public function entityKey(string $table, int $recordId, array $hints = []): string
    {
        return self::ENTITY . ':' . $recordId;
    }

    /** Owns no label, so it can never be asked to rebuild one. */
    public function enqueueNode(string $label, int $nodeId): array
    {
        return ['log' => [], 'queue' => []];
    }

    // -----------------------------------------------------------------------

    /**
     * @return array{0: string, 1: int}|null  [targetLabel, targetId], or null
     *                                        when this activity_type/id has no
     *                                        resolvable concept/content target
     */
    private function resolveTarget(string $activityType, ?int $activityId): ?array
    {
        if ($activityId === null) {
            return null;
        }

        return match ($activityType) {
            'content_view' => DB::table('content_master')->where('id', $activityId)->exists()
                ? ['TeachingResource', $activityId]
                : null,
            'practice', 'quiz' => DB::table('lms_concept')->where('id', $activityId)->exists()
                ? ['Concept', $activityId]
                : null,
            default => null,
        };
    }

    private function studentExists(int $studentId): bool
    {
        return DB::table('tblstudent')->where('id', $studentId)->exists();
    }

    /**
     * Any OTHER engagement row from this student, of an activity_type that
     * resolves to the same (label, id) target, besides the one being deleted.
     */
    private function survivingEngagementExists(int $studentId, string $targetLabel, int $targetId, int $excludeRecordId): bool
    {
        $types = $targetLabel === 'TeachingResource' ? ['content_view'] : ['practice', 'quiz'];

        return DB::table(self::TABLE)
            ->where('student_id', $studentId)
            ->where('activity_id', $targetId)
            ->whereIn('activity_type', $types)
            ->where('id', '!=', $excludeRecordId)
            ->exists();
    }

    private function intOrNull($value): ?int
    {
        return (is_numeric($value) && (int) $value > 0) ? (int) $value : null;
    }
}
