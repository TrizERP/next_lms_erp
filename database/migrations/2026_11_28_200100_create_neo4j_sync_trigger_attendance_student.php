<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attach the live-sync trigger to `attendance_student`, part of the
 * "Context Graph - Whole LMS Integration" task's Engagement follow-up.
 *
 * Mirrors `2026_10_10_120000_create_neo4j_sync_trigger_content_master.php`'s
 * approach exactly (a fresh, guarded migration per table rather than editing
 * `2026_08_21_100100_create_neo4j_sync_triggers.php`, which has already run)
 * — derives its watch/hint columns from the `attendance_student` entity spec
 * in `config/neo4j.php` added alongside this migration. Unlike
 * `lms_student_engagement`, this table's graph shape IS a plain column map
 * (one row -> one node -> one fixed-label edge, no per-row branching), so it
 * is a declarative `TableGraphProjection` entity, not a bespoke class.
 *
 * `attendance_student` had NO live or batch Neo4j sync at all before this —
 * confirmed via `config('neo4j.projections.triggered')` (table absent) and
 * the k12 batch-ingest scripts (which only ever loaded the term-aggregate
 * `result_student_attendance_master`, a different table, into a different,
 * batch-only edge).
 *
 * Idempotent: DROP TRIGGER IF EXISTS before every CREATE.
 */
return new class extends Migration
{
    private const TABLE = 'attendance_student';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $spec = config('neo4j.projections.entities.' . self::TABLE);

        if (! is_array($spec)) {
            return;
        }

        $columns = $this->columnsOf(self::TABLE);

        $watch = array_values(array_intersect(array_unique($this->watchColumns($spec)), $columns));
        $watch = array_values(array_diff($watch, ['id']));
        $watch = $watch !== [] ? $watch : ['id'];

        $hints = array_values(array_intersect(array_unique(['student_id', 'sub_institute_id']), $columns));

        $this->dropTriggers();

        $hintsNew = $this->jsonObject($hints, 'NEW');
        $hintsOld = $this->jsonObject($hints, 'OLD');

        $unchanged = implode(' AND ', array_map(
            fn ($c) => "NEW.`{$c}` <=> OLD.`{$c}`",
            $watch
        )) ?: '1 = 1';

        DB::unprepared($this->trigger('ai', 'AFTER INSERT', 'INSERT', 'NEW', $hintsNew));
        DB::unprepared($this->trigger('ad', 'AFTER DELETE', 'DELETE', 'OLD', $hintsOld));
        DB::unprepared($this->trigger('au', 'AFTER UPDATE', 'UPDATE', 'NEW', $hintsNew, "NOT ({$unchanged})"));
    }

    public function down(): void
    {
        $this->dropTriggers();
    }

    // -----------------------------------------------------------------------

    private function watchColumns(array $spec): array
    {
        $watch = array_values($spec['properties'] ?? []);

        foreach ($spec['relationships'] ?? [] as $rel) {
            $watch[] = $rel['from'][1];
            $watch[] = $rel['to'][1];
        }

        return $watch;
    }

    private function trigger(
        string $suffix,
        string $timing,
        string $operation,
        string $alias,
        string $hints,
        string $extraCondition = ''
    ): string {
        $table = self::TABLE;
        $guard = 'COALESCE(@neo4j_sync_off, 0) = 0'
            . ($extraCondition === '' ? '' : " AND {$extraCondition}");

        return "
            CREATE TRIGGER `neo4j_sync_{$table}_{$suffix}`
            {$timing} ON `{$table}` FOR EACH ROW
            BEGIN
                IF {$guard} THEN
                    INSERT INTO `sync_log`
                        (`table_name`, `operation_type`, `record_id`, `payload_json`, `status`, `retry_count`, `created_at`)
                    VALUES
                        ('{$table}', '{$operation}', {$alias}.`id`,
                         JSON_OBJECT(
                             'record_id',    {$alias}.`id`,
                             'event',        '{$operation}',
                             'source_table', '{$table}',
                             'data',         {$hints}
                         ),
                         'PENDING', 0, NOW());
                END IF;
            END
        ";
    }

    private function jsonObject(array $columns, string $alias): string
    {
        if ($columns === []) {
            return 'JSON_OBJECT()';
        }

        $pairs = implode(', ', array_map(
            fn ($c) => "'{$c}', {$alias}.`{$c}`",
            $columns
        ));

        return "JSON_OBJECT({$pairs})";
    }

    private function dropTriggers(): void
    {
        foreach (['ai', 'au', 'ad'] as $suffix) {
            DB::unprepared('DROP TRIGGER IF EXISTS `neo4j_sync_' . self::TABLE . "_{$suffix}`");
        }
    }

    /** @return string[] */
    private function columnsOf(string $table): array
    {
        return array_map(
            fn ($row) => $row->COLUMN_NAME,
            DB::select(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )
        );
    }
};
