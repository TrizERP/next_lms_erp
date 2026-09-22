<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of work an `lms_assignment` row is: an assignment, a worksheet or
 * a project.
 *
 * Worksheets and projects are assigned, submitted, annotated and graded
 * exactly the way assignments are -- same screens, same student inbox, same
 * flow -- so they are the same row with a different label rather than two more
 * tables that would have to reimplement all of it.
 *
 * Defaulting to 'assignment' is what keeps this additive: every existing row
 * and every existing query, none of which mention work_type, keep reading and
 * writing assignments exactly as before.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('lms_assignment', function (Blueprint $table) {
            $table->string('work_type', 20)->nullable()->default('assignment')->after('assignment_source_type');
        });
    }

    public function down()
    {
        Schema::table('lms_assignment', function (Blueprint $table) {
            $table->dropColumn('work_type');
        });
    }
};
