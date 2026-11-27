<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regenerate the `platform_workflows` live-sync trigger so it watches
 * `steps`, `created_at` and `updated_at`.
 *
 * WorkflowController::index() now reads chain data from Neo4j instead of SQL
 * (user request, 2026-11-25). `steps` (the actual approver/SLA/escalation
 * ladder) and the real `created_at`/`updated_at` (synced as `sql_created_at`/
 * `sql_updated_at` — see config/neo4j.php's comment on why) have been added
 * to `config('neo4j.projections.entities.platform_workflows.properties')` —
 * but the trigger installed by
 * 2026_11_25_110000_create_neo4j_sync_trigger_platform_workflows.php was
 * built before those columns existed in the spec, so an edit that ONLY
 * changes `steps` (the normal case — editing a chain's approvers without
 * touching its name/status/condition) would never have fired the UPDATE
 * trigger at all. Same idempotent DROP+CREATE template as
 * 2026_11_24_120000_resync_neo4j_trigger_fees_collect_is_deleted.php — that
 * migration is the precedent for this exact situation, and is left untouched.
 */
return new class extends Migration
{
    private const TABLE = 'platform_workflows';

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
