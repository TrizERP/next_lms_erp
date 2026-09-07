<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the LMS Engagement REST APIs (Leader Board + Social &
 * Collaborative).
 *
 * Purely additive: no column is added, changed or dropped and no data is
 * touched. The three tables have carried NO index beyond their primary key
 * since 2021, while every query in the new API filters on
 * (sub_institute_id, syear) and the class ranking groups the points ledger by
 * user - all full scans today.
 *
 * Both up() and down() check information_schema first, so re-running is a
 * no-op rather than an error.
 */
return new class extends Migration
{
    /** @var array<string,array<string,array<int,string>>> table => index => columns */
    private array $indexes = [
        'lb_points' => [
            'lb_points_tenant_year_user_idx'   => ['sub_institute_id', 'syear', 'user_id'],
            'lb_points_tenant_year_module_idx' => ['sub_institute_id', 'syear', 'module_name'],
        ],
        'lb_master' => [
            'lb_master_tenant_standard_idx' => ['sub_institute_id', 'standard_id', 'module_name'],
        ],
        'lms_doubt' => [
            'lms_doubt_tenant_year_idx' => ['sub_institute_id', 'syear'],
            'lms_doubt_user_idx'        => ['user_id'],
        ],
        'lms_doubt_conversation' => [
            'lms_doubt_conversation_doubt_idx'  => ['doubt_id'],
            'lms_doubt_conversation_tenant_idx' => ['sub_institute_id', 'doubt_id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if ($this->indexExists($table, $name)) {
                    continue;
                }

                $columnList = implode(', ', array_map(static fn ($column) => "`{$column}`", $columns));
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$columnList})");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if ($this->indexExists($table, $name)) {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
                }
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
