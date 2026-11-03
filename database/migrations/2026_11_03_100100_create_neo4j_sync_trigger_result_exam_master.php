<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-attach (not first-attach) the live-sync trigger to `result_exam_master`.
 *
 * Knowledge Graph coverage pass (2026-11-03): this table was already in
 * `config('neo4j.projections.triggered')` and already had triggers from
 * `2026_08_21_100100_create_neo4j_sync_triggers.php`, but that migration
 * derived its watch/hint columns from the spec as it existed back then —
 * which had no `relationships` key. Today's edit adds
 * `OF_EXAM_TYPE_CATEGORY` to the spec, so the trigger must be reinstalled to
 * pick up the new FK column (`ExamType`) as a watched/hinted column.
 * Idempotent (DROP TRIGGER IF EXISTS before every CREATE), so re-running the
 * derivation here is safe even though the table was never "unwired".
 */
return new class extends Migration
{
    private const TABLE = 'result_exam_master';

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

        [$watch, $hints] = $this->deriveWatchAndHints($spec);

        $watch = array_values(array_intersect(array_unique($watch), $columns));
        $watch = array_values(array_diff($watch, ['Id']));
        $watch = $watch !== [] ? $watch : ['Id'];

        $hints = array_values(array_intersect(array_unique($hints), $columns));

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

    private function deriveWatchAndHints(array $spec): array
    {
        $watch = array_values($spec['properties'] ?? []);
        $hints = [];

        foreach ($spec['relationships'] ?? [] as $rel) {
            $watch[] = $rel['from'][1];
            $watch[] = $rel['to'][1];

            if (($spec['edges_only'] ?? false) === true) {
                $hints[] = $rel['from'][1];
                $hints[] = $rel['to'][1];
            }
        }

        if (isset($spec['key_column'])) {
            $watch[] = $spec['key_column'];
            $hints[] = $spec['key_column'];
        }

        $hints[] = 'SubInstituteId';

        return [$watch, $hints];
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
                        ('{$table}', '{$operation}', {$alias}.`Id`,
                         JSON_OBJECT(
                             'record_id',    {$alias}.`Id`,
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
