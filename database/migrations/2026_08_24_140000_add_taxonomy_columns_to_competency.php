<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds real `category`, `sub_category` and `department` columns to
 * `competency`, so the Competency Library create/edit form's own fields for
 * these actually persist and round-trip.
 *
 * G2G's own `CompetencyLibraryCrudController` never persists these three
 * (its `store()`/`update()` never write them, and its `shape()` always
 * returns `category` as the linked framework's name and `sub_category`/
 * `department` as hardcoded null - confirmed by reading that controller's
 * own doc-comment and code directly) - the form fields are dead there too.
 * This project's user asked for that specific behavior to be corrected
 * rather than preserved, so this migration and the accompanying controller
 * change are a deliberate deviation from G2G parity for this one screen,
 * not a porting mistake.
 *
 * Purely additive and guarded, so nothing existing is affected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competency', function (Blueprint $table) {
            if (!Schema::hasColumn('competency', 'category')) {
                $table->string('category', 191)->nullable()->after('description');
            }
            if (!Schema::hasColumn('competency', 'sub_category')) {
                $table->string('sub_category', 191)->nullable()->after('category');
            }
            if (!Schema::hasColumn('competency', 'department')) {
                $table->string('department', 191)->nullable()->after('sub_category');
            }
        });
    }

    /**
     * Deliberately does not drop columns - matches this module's other
     * additive migrations (e.g. `2026_08_24_090300_add_competency_library_crud_columns.php`):
     * by the time this rolls back these columns may hold real data.
     */
    public function down(): void
    {
        // no-op
    }
};
