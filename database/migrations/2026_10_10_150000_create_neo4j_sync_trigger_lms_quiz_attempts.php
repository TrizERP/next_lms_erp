<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attach the live-sync trigger to the new `lms_quiz_attempts` table.
 *
 * Same reasoning as `2026_10_10_120000_create_neo4j_sync_trigger_content_master`:
 * `2026_08_21_100100_create_neo4j_sync_triggers.php` already ran and only ever
 * creates triggers for whatever was in config('neo4j.projections.triggered')
 * at the time it ran. `lms_quiz_attempts` is a table created today, after that
 * migration, so it needs its own trigger migration rather than an edit to
 * migration history. Copied structure, not shared code, for the same reason
 * the content_master migration gives: touching the original migration's
 * helpers would touch every table it already covers.
 *
 * Idempotent: DROP TRIGGER IF EXISTS before every CREATE.
 */
return new class extends Migration
{
    private const TABLE = 'lms_quiz_attempts';

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

        $watch = array_values(array_intersect(array_unique($this->watchColumns($spec)), $columns));
        $watch = array_values(array_diff($watch, ['id']));   // the PK never changes
        $watch = $watch !== [] ? $watch : ['id'];

        $hints = array_values(array_intersect(array_unique(['sub_institute_id']), $columns));

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

    /** Every column the spec's properties and relationship endpoints read. */
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
