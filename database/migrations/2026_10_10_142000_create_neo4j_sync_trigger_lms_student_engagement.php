<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attach the live-sync trigger to `lms_student_engagement`.
 *
 * Mirrors `2026_10_10_120000_create_neo4j_sync_trigger_content_master`'s
 * approach (its own guarded migration rather than editing
 * `2026_08_21_100100_create_neo4j_sync_triggers`, which has already run) but
 * cannot reuse that file's config-driven column derivation: this table has no
 * `config('neo4j.projections.entities.*')` spec, because its graph shape is
 * NOT a plain column map — which of :Concept / :TeachingResource the edge
 * points at depends on the row's own `activity_type`, something the
 * declarative `TableGraphProjection` cannot express. `EngagementGraphProjection`
 * (bespoke, registered in `ProjectionRegistry::BESPOKE`) owns it instead, the
 * same escape hatch `StudentGraphProjection`/`ResultGraphProjection`/
 * `StaffGraphProjection` already use — and precisely because it is bespoke,
 * its watch/hint columns are hand-listed here, the same way the ORIGINAL
 * trigger migration hand-lists them for `tblstudent`/`lms_online_exam`/
 * `tbluser` rather than deriving them from a config entry that does not exist.
 *
 * Watched columns: `student_id`, `activity_type`, `activity_id`,
 * `sub_institute_id` — everything `EngagementGraphProjection::enqueue()`
 * reads to decide the edge. `time_spent_seconds`/`metadata`/`completed_at`
 * changing alone queues nothing, because they do not change what the row
 * means for the graph.
 *
 * Hints carry the same four columns so `delete()` can rebuild the edge
 * identity after the row is gone (a DELETE trigger cannot re-read the table).
 *
 * Idempotent: DROP TRIGGER IF EXISTS before every CREATE.
 */
return new class extends Migration
{
    private const TABLE = 'lms_student_engagement';

    private const WATCH = ['student_id', 'activity_type', 'activity_id', 'sub_institute_id'];

    private const HINTS = ['student_id', 'activity_type', 'activity_id', 'sub_institute_id'];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = $this->columnsOf(self::TABLE);

        $watch = array_values(array_intersect(self::WATCH, $columns));
        $watch = $watch !== [] ? $watch : ['id'];
        $hints = array_values(array_intersect(self::HINTS, $columns));

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
