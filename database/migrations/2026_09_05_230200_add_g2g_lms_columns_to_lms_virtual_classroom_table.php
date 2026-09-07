<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2G LMS migration — Package 2 (Assignments, Sessions & Calendar).
 *
 * `lms_virtual_classroom` is REUSED as the Sessions & Calendar table (it is
 * the only existing table carrying `event_date` + `from_time`/`to_time`/
 * `url`, matching G2G's own choice of `App\Http\Controllers\Api\
 * LmsSessionController`). This adds only what G2G's session screen needs
 * and this table does not already have.
 *
 * Every column add is guarded with `Schema::hasColumn()` so the migration is
 * safe to re-run and cannot collide with a column another package's
 * migration might add first.
 *
 * `grade_id` / `standard_id` / `subject_id` / `chapter_id` / `topic_id` are
 * relaxed to nullable below (`->nullable()->change()`, `doctrine/dbal` is
 * present in `vendor/doctrine/dbal`, confirmed before writing this). Data
 * risk was checked first: every existing row already has non-null values
 * (the columns are currently `NOT NULL`), so relaxing to nullable cannot
 * violate any existing row — it only permits future NULLs, which is what
 * G2G's Sessions screen needs for a general session not tied to one
 * grade/subject/chapter/topic. This also resolves a pre-existing
 * inconsistency: `2023_06_12_233617_add_foreign_key_into_inventory_item_lms_mapping_type_table`
 * already put `onDelete('set null')` foreign keys on these `NOT NULL`
 * columns, which would itself fail at the database level the moment one of
 * those parent rows was actually deleted.
 *
 * No FK is added on the new `trainer_id` column — see its own comment below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_virtual_classroom', function (Blueprint $table) {
            if (!Schema::hasColumn('lms_virtual_classroom', 'session_type')) {
                $table->string('session_type', 20)->default('virtual')->after('room_name');
            }
            if (!Schema::hasColumn('lms_virtual_classroom', 'trainer_name')) {
                $table->string('trainer_name', 191)->nullable()->after('description');
            }
            if (!Schema::hasColumn('lms_virtual_classroom', 'trainer_email')) {
                $table->string('trainer_email', 191)->nullable()->after('trainer_name');
            }
            if (!Schema::hasColumn('lms_virtual_classroom', 'venue')) {
                $table->string('venue', 191)->nullable()->after('trainer_email');
            }
            if (!Schema::hasColumn('lms_virtual_classroom', 'seats_total')) {
                // Null = uncapped session; the controller treats it that way.
                $table->integer('seats_total')->nullable()->after('venue');
            }
            if (!Schema::hasColumn('lms_virtual_classroom', 'notes')) {
                $table->text('notes')->nullable()->after('seats_total');
            }
            if (!Schema::hasColumn('lms_virtual_classroom', 'trainer_id')) {
                /*
                 * NO FOREIGN KEY YET — deliberate.
                 *
                 * `lms_trainers` (owned by Package 4, Administration &
                 * Governance) does not exist in this tree yet. Adding a hard
                 * FK to a table that is not there would fail this migration
                 * outright, and adding it as a soft/unenforced reference now
                 * means Package 4 can add the FK constraint in their own
                 * migration once `lms_trainers` exists, without this
                 * migration needing to be rewritten. Plain nullable
                 * `unsignedBigInteger`, no constraint.
                 */
                $table->unsignedBigInteger('trainer_id')->nullable()->after('trainer_email');
            }

            // Audit + soft-delete. `created_at` and `created_by` already exist
            // on this table (see the original create-table migration); only
            // `updated_at`/`updated_by`/`deleted_at`/`deleted_by` are missing.
            if (!Schema::hasColumn('lms_virtual_classroom', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_ip');
            }
            if (!Schema::hasColumn('lms_virtual_classroom', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('updated_at');
            }
            if (!Schema::hasColumn('lms_virtual_classroom', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_by');
            }
            if (!Schema::hasColumn('lms_virtual_classroom', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            }
        });

        // Relax to nullable — a general session need not be tied to one
        // grade/subject/chapter/topic. Same integer type as the original
        // create-table migration, only nullability changes.
        Schema::table('lms_virtual_classroom', function (Blueprint $table) {
            $table->integer('grade_id')->nullable()->change();
            $table->integer('standard_id')->nullable()->change();
            $table->integer('subject_id')->nullable()->change();
            $table->integer('chapter_id')->nullable()->change();
            $table->integer('topic_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lms_virtual_classroom', function (Blueprint $table) {
            foreach ([
                'session_type', 'trainer_name', 'trainer_email', 'venue',
                'seats_total', 'notes', 'trainer_id',
                'updated_at', 'updated_by', 'deleted_at', 'deleted_by',
            ] as $column) {
                if (Schema::hasColumn('lms_virtual_classroom', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Nullability is intentionally NOT reverted here: reverting would
        // require every row (including ones this feature legitimately wrote
        // with a NULL grade/subject/etc.) to already have a non-null value,
        // which cannot be guaranteed by this migration alone.
    }
};
