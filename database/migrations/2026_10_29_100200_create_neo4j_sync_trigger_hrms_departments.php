<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attach the live-sync trigger to `hrms_departments` only.
 * See 2026_10_29_100000_create_neo4j_sync_trigger_division for the full reasoning.
 *
 * Note: `hrms_departments` has `deleted_at` (soft delete) but no model-level
 * soft-delete handling is assumed here — a physical DELETE still fires the
 * `ad` trigger below exactly like every other table in this file; a soft
 * delete (UPDATE ... SET deleted_at = NOW()) fires the `au` trigger instead
 * and is watched like any other column change, same as `status`.
 */
return new class extends Migration
{
    private const TABLE = 'hrms_departments';

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
        $watch = array_values(array_diff($watch, ['id']));
        $watch = $watch !== [] ? $watch : ['id'];
        $watch = array_unique(array_merge($watch, array_intersect(['deleted_at'], $columns)));

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

        $hints[] = 'sub_institute_id';

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
