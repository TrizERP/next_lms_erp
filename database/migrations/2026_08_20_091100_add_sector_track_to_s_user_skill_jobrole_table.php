<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `s_user_skill_jobrole` in hp_erp's live schema also has `sector`/`track`
 * columns that its original CREATE-TABLE migration file didn't include
 * (added directly to the live schema, same situation as
 * 2026_08_20_091000_add_missing_columns_to_skill_jobrole_task_tables.php).
 */
class AddSectorTrackToSUserSkillJobroleTable extends Migration
{
    public function up()
    {
        Schema::table('s_user_skill_jobrole', function (Blueprint $table) {
            $table->string('sector', 191)->nullable()->after('id');
            $table->string('track', 191)->nullable()->after('sector');
        });
    }

    public function down()
    {
        Schema::table('s_user_skill_jobrole', function (Blueprint $table) {
            $table->dropColumn(['sector', 'track']);
        });
    }
}
