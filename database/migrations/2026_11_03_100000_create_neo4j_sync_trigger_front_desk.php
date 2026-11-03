<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attach the live-sync trigger to `front_desk` only.
 *
 * Knowledge Graph coverage pass (2026-11-03): `front_desk` (:FrontDeskEntry)
 * had live nodes from the one-off batch load but no ongoing trigger and no
 * relationship to :StuDetail — `config/neo4j.php` gained its first real
 * entity spec for this table today, so it needs its own trigger migration,
 * same reasoning as every other per-table trigger migration in this
 * directory (`2026_08_21_100100_create_neo4j_sync_triggers.php` already ran
 * and only ever creates triggers for whatever was in
 * `config('neo4j.projections.triggered')` at the time it ran).
 *
 * Idempotent: DROP TRIGGER IF EXISTS before every CREATE.
 */
return new class extends Migration
{
    private const TABLE = 'front_desk';

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
        $watch = array_values(array_diff($watch, ['ID']));
        $watch = $watch !== [] ? $watch : ['ID'];

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
                        ('{$table}', '{$operation}', {$alias}.`ID`,
                         JSON_OBJECT(
                             'record_id',    {$alias}.`ID`,
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
