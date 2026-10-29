<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attach the live-sync trigger to `division` only.
 *
 * Same reasoning as `2026_10_12_100000_create_neo4j_sync_trigger_s_competency_frameworks`:
 * `2026_08_21_100100_create_neo4j_sync_triggers.php` already ran and only ever
 * creates triggers for whatever was in `config('neo4j.projections.triggered')`
 * at the time it ran. `division` is one of four Institute ERP foundation
 * tables the 2026-10-29 sync audit found had NO live trigger at all — only a
 * one-time Pipeline C batch load from 2026-08-10, since gone 79+ days stale
 * and confirmed incomplete. User decision 2026-10-29: add one, the same
 * mechanism every other table here uses.
 *
 * Idempotent: DROP TRIGGER IF EXISTS before every CREATE.
 */
return new class extends Migration
{
    private const TABLE = 'division';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;   // table absent in this database — nothing to attach to
        }

        $spec = config('neo4j.projections.entities.' . self::TABLE);

        if (! is_array($spec)) {
            return;   // config entry missing — nothing to derive columns from
        }

        $columns = $this->columnsOf(self::TABLE);

        [$watch, $hints] = $this->deriveWatchAndHints($spec);

        $watch = array_values(array_intersect(array_unique($watch), $columns));
        $watch = array_values(array_diff($watch, ['id']));   // the PK never changes
        $watch = $watch !== [] ? $watch : ['id'];

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
