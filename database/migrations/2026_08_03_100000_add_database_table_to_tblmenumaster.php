<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tblmenumaster.database_table` was added straight to the database and never
 * captured in a migration, so `migrate:fresh` produced a schema on which the
 * legacy /Onboarding screen (tourController@Onboarding) fatals. The onboarding
 * API reads the same column as a fallback proof source, so the column is
 * reconciled here. Guarded because it already exists on live databases.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        Schema::table('tblmenumaster', function (Blueprint $table) {
            if (! Schema::hasColumn('tblmenumaster', 'database_table')) {
                $table->string('database_table', 50)->nullable()->after('menu_type');
            }
        });
    }

    public function down()
    {
        // Intentionally left empty: the column pre-dates this migration on every
        // live database, so dropping it on rollback would destroy existing data.
    }
};
