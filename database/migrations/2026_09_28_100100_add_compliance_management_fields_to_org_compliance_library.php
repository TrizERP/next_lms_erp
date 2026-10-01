<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance Management foundation, phase 1b: extends the existing
 * `org_compliance_library` table in place (per product decision - do not
 * fork a parallel table) with the columns needed for category, a real
 * department FK, lifecycle status/priority, and recurrence.
 *
 * `department` (free-text string) and `attachment` (single file) are left
 * untouched - the live controller/frontend still read/write them, and
 * ripping them out is explicitly out of scope for this pass (data model
 * only, no controller/API/frontend changes). `department_id` is added
 * alongside as the FK a future controller pass will switch reads/writes to,
 * following the exact pattern `org_disciplinary_library.department_id`
 * already uses against the same `hrms_departments` table. `attachment`'s
 * data is carried into the new `compliance_evidence` table by the sibling
 * migration 2026_09_28_100200, again without removing the original column.
 *
 * All columns are nullable/defaulted so every existing row (and the
 * unmodified controller, which never sets any of them) keeps working
 * unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_compliance_library', function (Blueprint $table) {
            if (!Schema::hasColumn('org_compliance_library', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->index()->after('standard_name');
            }
            if (!Schema::hasColumn('org_compliance_library', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->index()->after('department');
            }
            if (!Schema::hasColumn('org_compliance_library', 'status')) {
                $table->string('status', 40)->default('Upcoming')->index()->after('frequency');
            }
            if (!Schema::hasColumn('org_compliance_library', 'priority')) {
                $table->string('priority', 20)->default('Medium')->index()->after('status');
            }
            if (!Schema::hasColumn('org_compliance_library', 'next_due_date')) {
                $table->date('next_due_date')->nullable()->after('duedate');
            }
            if (!Schema::hasColumn('org_compliance_library', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            if (!Schema::hasColumn('org_compliance_library', 'completed_by')) {
                $table->unsignedBigInteger('completed_by')->nullable()->index();
            }
            if (!Schema::hasColumn('org_compliance_library', 'parent_compliance_id')) {
                // Links a generated recurring cycle back to the cycle it was
                // spawned from, so history (2025 -> 2026 -> 2027 cycles) can be
                // walked without duplicating the template's own data.
                $table->unsignedBigInteger('parent_compliance_id')->nullable()->index();
            }
        });

        // FKs added as their own statement (not inside the closure above) so a
        // partial prior run - some columns added, FK step failed - can be
        // retried without erroring on "column already exists".
        $this->addForeignKeyIfMissing(
            'org_compliance_library', 'category_id', 'compliance_categories', 'id',
            'org_compliance_library_category_id_foreign'
        );
        $this->addForeignKeyIfMissing(
            'org_compliance_library', 'department_id', 'hrms_departments', 'id',
            'org_compliance_library_department_id_foreign'
        );
        $this->addForeignKeyIfMissing(
            'org_compliance_library', 'parent_compliance_id', 'org_compliance_library', 'id',
            'org_compliance_library_parent_compliance_id_foreign'
        );

        $this->backfillDepartmentId();
        $this->backfillInitialStatus();
    }

    public function down(): void
    {
        Schema::table('org_compliance_library', function (Blueprint $table) {
            foreach ([
                'org_compliance_library_category_id_foreign',
                'org_compliance_library_department_id_foreign',
                'org_compliance_library_parent_compliance_id_foreign',
            ] as $fk) {
                if ($this->foreignKeyExists('org_compliance_library', $fk)) {
                    $table->dropForeign($fk);
                }
            }

            foreach ([
                'category_id', 'department_id', 'status', 'priority',
                'next_due_date', 'completed_at', 'completed_by', 'parent_compliance_id',
            ] as $column) {
                if (Schema::hasColumn('org_compliance_library', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Matches each existing row's free-text `department` to a real
     * `hrms_departments` row in the same tenant by exact (trimmed,
     * case-insensitive) name. Rows with no match (typo, department since
     * renamed/deleted) are left with `department_id = NULL` rather than
     * guessed - the free-text `department` column still carries the
     * original value, so nothing is lost.
     */
    private function backfillDepartmentId(): void
    {
        DB::table('org_compliance_library as c')
            ->join('hrms_departments as d', function ($join) {
                $join->on('d.sub_institute_id', '=', 'c.sub_institute_id')
                    ->whereRaw('LOWER(TRIM(d.department)) = LOWER(TRIM(c.department))');
            })
            ->whereNull('c.department_id')
            ->whereNotNull('c.department')
            ->update(['c.department_id' => DB::raw('d.id')]);
    }

    /**
     * Historical rows all defaulted to `status = 'Upcoming'` when the column
     * was added; this gives them a first real value instead (still a coarse
     * one - the live time-based derivation lands with the API/controller
     * pass, per `ComplianceLibraryRecord::deriveStatus()`). Never touches a
     * row whose due date is missing.
     */
    private function backfillInitialStatus(): void
    {
        DB::table('org_compliance_library')
            ->whereNotNull('duedate')
            ->where('duedate', '<', now()->toDateString())
            ->where('status', 'Upcoming')
            ->update(['status' => 'Overdue']);

        DB::table('org_compliance_library')
            ->whereNotNull('duedate')
            ->where('duedate', '>=', now()->toDateString())
            ->where('duedate', '<=', now()->addDays(30)->toDateString())
            ->where('status', 'Upcoming')
            ->update(['status' => 'Due Soon']);
    }

    private function addForeignKeyIfMissing(
        string $table, string $column, string $refTable, string $refColumn, string $fkName
    ): void {
        if ($this->foreignKeyExists($table, $fkName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $refTable, $refColumn, $fkName) {
            $blueprint->foreign($column, $fkName)
                ->references($refColumn)->on($refTable)
                ->onDelete('NO ACTION')->onUpdate('NO ACTION');
        });
    }

    private function foreignKeyExists(string $table, string $fkName): bool
    {
        $found = DB::select(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = "FOREIGN KEY"
              LIMIT 1',
            [$table, $fkName]
        );

        return $found !== [];
    }
};
