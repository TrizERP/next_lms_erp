<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attach the live-sync trigger to `s_competency_framework_items` only.
 *
 * Same reasoning as `2026_10_10_120000_create_neo4j_sync_trigger_content_master`:
 * `2026_08_21_100100_create_neo4j_sync_triggers.php` already ran and only ever
 * creates triggers for whatever was in `config('neo4j.projections.triggered')`
 * at the time it ran. `s_competency_framework_items` (the join behind
 * `(:CompetencyFramework)-[:HAS_COMPETENCY]->(:Competency)`) is a table added
 * to that config today, so it needs its own trigger migration rather than an
 * edit to migration history.
 *
 * UNLIKE the other two tables added alongside this one, this spec IS
 * `edges_only` (a pure join, no node of its own — see config/neo4j.php). That
 * is exactly why this file derives watch/hints the GENERAL way (mirroring
 * `2026_08_21_100100`'s auto-derive branch) rather than copying the simpler
 * `['sub_institute_id']`-only shortcut the content_master/lms_quiz_attempts
 * per-table migrations used: an edge-only row's endpoint columns
 * (`framework_id`, `competency_id`) MUST ride along as hints, because once
 * the row is DELETEd there is nothing left to re-read, and
 * `TableGraphProjection::delete()` rebuilds the edge to remove purely from
 * those hints. Omitting them (as the simpler shortcut would) would silently
 * break every DELETE on this table — the edge would never be retracted from
 * the graph after its MariaDB row was removed.
 *
 * Idempotent: DROP TRIGGER IF EXISTS before every CREATE.
 */
return new class extends Migration
{
    private const TABLE = 's_competency_framework_items';

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

        // NULL-safe comparison: `<=>` treats NULL = NULL as true, so a row whose
        // watched columns are all still NULL is correctly unchanged.
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

    /**
     * Same derivation as `2026_08_21_100100_create_neo4j_sync_triggers.php`'s
     * auto-derive branch — kept here rather than shared, for the same reason
     * that migration's own docblock gives: it has already run, so refactoring
     * its helpers out would be touching history for every table it covers.
     *
     * @return array{0: string[], 1: string[]} [watch, hints]
     */
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
