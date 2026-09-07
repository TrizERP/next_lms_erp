<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'lms_assignments' => ['course_id'],
            'lms_certificates' => ['course_id'],
            'lms_course_discussions' => ['course_id'],
            'lms_course_prerequisites' => ['prerequisite_course_id'],
        ];

        foreach ($columns as $table => $names) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($names as $column) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` INT UNSIGNED NOT NULL");
            }
        }
    }

    public function down(): void
    {
        // This aligns references with sub_std_map.id; reverting would reintroduce a mismatch.
    }
};