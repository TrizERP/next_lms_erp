<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Capture every change to a K12 source table into `sync_log`, at the database.
 *
 * -----------------------------------------------------------------------------
 * WHY TRIGGERS AND NOT CONTROLLER CALLS
 * -----------------------------------------------------------------------------
 * Because controller calls were tried and they lost data. `GraphSync` was wired
 * into two API controllers; MariaDB has at least fifteen paths that write a
 * student — the legacy web controller, two admission-confirmation flows, four
 * import sites, bulk edits, transfers, console commands, plain SQL. A student
 * added through any of the other thirteen (tblstudent#281472, found 2026-08-21)
 * committed to MariaDB and never produced an outbox row, so the graph simply
 * never heard about them. Enumerating writers is a losing game: the next one
 * added regresses it again.
 *
 * Every one of those paths ends in an INSERT, and an INSERT fires a trigger.
 * That is the whole argument.
 *
 * -----------------------------------------------------------------------------
 * WHAT THE TRIGGERS DO, AND DELIBERATELY DO NOT DO
 * -----------------------------------------------------------------------------
 * They record one fact — "row X of table T changed" — plus the few columns that
 * cannot be read back after a DELETE. No graph shape, no labels, no Cypher.
 * `GraphDrain` hands the event to the row's projection, which re-reads MariaDB
 * and decides what the graph should look like. All of that stays in PHP where
 * it is reviewable and testable; the SQL here never has to change when the
 * graph shape does.
 *
 * -----------------------------------------------------------------------------
 * WHY `data` HOLDS SO LITTLE
 * -----------------------------------------------------------------------------
 * The envelope matches the projected node events exactly —
 * {record_id, event, source_table, data} against
 * {record_id, event, node_label, data} — so every row in `sync_log` is
 * self-describing and the column has one readable shape.
 *
 * But `data` deliberately carries ONLY what cannot be read back after the row is
 * deleted (the tenant, and an enrolment's owner). It is NOT a copy of the row.
 * Serialising every projected column here would mean writing the graph's shape
 * into SQL in a second place, where it would drift from the PHP projection the
 * moment either changed — and it still could not produce the full node payload,
 * because `displayLabel`, the per-enrolment fan-out and the type casting are all
 * decided by the projection after re-reading MariaDB.
 *
 * The full node payload lives on the rows the projection writes, keyed by LABEL
 * rather than table:
 *
 *   SELECT * FROM sync_log WHERE table_name = 'Student'   -- full node data
 *   SELECT * FROM sync_log WHERE table_name = 'tblstudent' -- "this row changed"
 *
 * Re-reading rather than trusting a snapshot is also what makes a replay
 * correct: draining a week-old event syncs the student as they are NOW, not as
 * they were when the trigger fired.
 *
 * The event is written INSIDE the caller's transaction, which is what makes the
 * outbox correct: a rolled-back student takes their graph event down with them,
 * and a crash after COMMIT cannot lose one.
 *
 * -----------------------------------------------------------------------------
 * TWO SAFETY VALVES
 * -----------------------------------------------------------------------------
 * `@neo4j_sync_off` — set `SET @neo4j_sync_off = 1;` on a connection and its
 * writes queue nothing. For bulk imports, where 200,000 row events would be
 * 200,000 pointless re-projections of the same handful of subjects; import with
 * it set, then run one `neo4j:reconcile --fix` pass.
 *
 * UPDATE triggers fire only when a column the projection actually reads has
 * changed, derived from `config/neo4j.php` (`projections` key) rather than hand-listed,
 * so the two cannot drift apart. Without this, every fee posting that touches
 * `tblstudent` would queue a graph event.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->definitions() as $table => $spec) {
            $this->dropTriggers($table);

            $hintsNew = $this->jsonObject($spec['hints'], 'NEW');
            $hintsOld = $this->jsonObject($spec['hints'], 'OLD');

            // NULL-safe comparison: `<=>` treats NULL = NULL as true, so a row
            // whose watched columns are all still NULL is correctly unchanged.
            $unchanged = implode(' AND ', array_map(
                fn ($c) => "NEW.`{$c}` <=> OLD.`{$c}`",
                $spec['watch']
            )) ?: '1 = 1';

            DB::unprepared($this->trigger($table, 'ai', 'AFTER INSERT', 'INSERT', 'NEW', $hintsNew));
            DB::unprepared($this->trigger($table, 'ad', 'AFTER DELETE', 'DELETE', 'OLD', $hintsOld));
            DB::unprepared($this->trigger($table, 'au', 'AFTER UPDATE', 'UPDATE', 'NEW', $hintsNew, "NOT ({$unchanged})"));
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->definitions()) as $table) {
            $this->dropTriggers($table);
        }
    }

    // -----------------------------------------------------------------------

    private function trigger(
        string $table,
        string $suffix,
        string $timing,
        string $operation,
        string $alias,
        string $hints,
        string $extraCondition = ''
    ): string {
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

    private function dropTriggers(string $table): void
    {
        foreach (['ai', 'au', 'ad'] as $suffix) {
            DB::unprepared("DROP TRIGGER IF EXISTS `neo4j_sync_{$table}_{$suffix}`");
        }
    }

    /**
     * Which tables get triggers, which columns each event carries, and which
     * columns an UPDATE has to touch to be worth queueing.
     *
     * Everything except the two student tables is derived from the projection
     * specs, so adding an entity to `config/neo4j.php` (`projections` key) and listing
     * it under `triggered` is all it takes — there is no second list of columns
     * to keep in step.
     *
     * @return array<string, array{hints: string[], watch: string[]}>
     */
    private function definitions(): array
    {
        // The student projection spans two tables at two grains, so its columns
        // are named here rather than derived. `student_id` is a hint because an
        // enrolment's owner cannot be read back once the row is deleted.
        $definitions = [
            'tblstudent' => [
                'hints' => ['sub_institute_id'],
                'watch' => ['first_name', 'middle_name', 'last_name', 'email', 'mobile', 'admission_year', 'sub_institute_id'],
            ],
            'tblstudent_enrollment' => [
                'hints' => ['student_id', 'sub_institute_id'],
                'watch' => ['student_id', 'grade_id', 'standard_id', 'section_id', 'syear', 'sub_institute_id'],
            ],
            // Also bespoke: an attempt fans out across every enrolment its
            // person holds, so ResultGraphProjection owns it rather than a
            // column-map spec, and there is no config entry to derive from.
            'lms_online_exam' => [
                'hints' => ['student_id'],
                'watch' => ['student_id', 'question_paper_id', 'total_right', 'total_wrong', 'obtain_marks'],
            ],
            // Bespoke for the same reason: `tbluser` maps to :Teacher for the 118
            // the reference ingest claimed and :Staff for the other 4,653, so
            // StaffGraphProjection owns it and there is no column-map spec to
            // derive the watch list from.
            //
            // The watch list deliberately EXCLUDES `last_login`, which every
            // login updates — this is the hottest table in the schema and
            // queueing a graph event per sign-in would swamp the drain with
            // events that change nothing in the graph.
            'tbluser' => [
                'hints' => ['sub_institute_id'],
                'watch' => [
                    'user_name', 'first_name', 'middle_name', 'last_name', 'email', 'mobile',
                    'gender', 'user_profile_id', 'department_id', 'jobtitle_id', 'employee_no',
                    'qualification', 'occupation', 'joined_date', 'relieving_date',
                    'reporting_manager_id', 'subject_ids', 'allocated_standards',
                    'total_lecture', 'status', 'is_admin', 'sub_institute_id',
                ],
            ],
        ];

        foreach ((array) config('neo4j.projections.triggered', []) as $table) {
            if (isset($definitions[$table])) {
                continue;
            }

            $spec = config("neo4j.projections.entities.{$table}");

            if (! is_array($spec)) {
                continue;
            }

            // An edge-only spec (a join table) has no `properties` at all.
            $watch = array_values($spec['properties'] ?? []);

            $hints = [];

            foreach ($spec['relationships'] ?? [] as $rel) {
                $watch[] = $rel['from'][1];
                $watch[] = $rel['to'][1];

                // For an edge-only row the endpoints must ride along in the
                // event: once the row is deleted there is nothing left to
                // re-read, and `delete()` rebuilds the edge from the hints in
                // order to remove it.
                if (($spec['edges_only'] ?? false) === true) {
                    $hints[] = $rel['from'][1];
                    $hints[] = $rel['to'][1];
                }
            }

            // A column-keyed node (:Subject) needs its key in the event: after
            // the mapping row is deleted there is no way to look it up.
            if (isset($spec['key_column'])) {
                $watch[] = $spec['key_column'];
                $hints[] = $spec['key_column'];
            }

            $hints[] = 'sub_institute_id';

            $definitions[$table] = ['hints' => $hints, 'watch' => $watch];
        }

        // Only ever reference columns that exist: these are legacy tables that
        // differ between deployments, and a trigger naming a missing column
        // fails the whole migration.
        $resolved = [];

        foreach ($definitions as $table => $spec) {
            $columns = $this->columnsOf($table);

            if ($columns === []) {
                continue;   // table absent in this database
            }

            $watch = array_values(array_intersect(array_unique($spec['watch']), $columns));
            $watch = array_values(array_diff($watch, ['id']));   // the PK never changes

            $resolved[$table] = [
                'hints' => array_values(array_intersect(array_unique($spec['hints']), $columns)),
                'watch' => $watch !== [] ? $watch : ['id'],
            ];
        }

        return $resolved;
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
