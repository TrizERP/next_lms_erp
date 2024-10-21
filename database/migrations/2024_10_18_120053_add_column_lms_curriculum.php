<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lms_curriculum', function (Blueprint $table) {
            //
            if (Schema::hasColumn('lms_curriculum', 'subject_curricula')) {
                $table->dropColumn('subject_curricula');
            }
            $table->mediumText('objective')->after('model_integration')->nullable();
            $table->mediumText('chapter')->after('objective')->nullable();
            $table->mediumText('outcome')->after('chapter')->nullable();
            $table->mediumText('assessment_tool')->after('outcome')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lms_curriculum', function (Blueprint $table) {
            //
            $table->mediumText('subject_curricula')->after('holistic_curriculum')->nullable();
            $table->dropColumn('objective');
            $table->dropColumn('chapter');
            $table->dropColumn('outcome');
            $table->dropColumn('assessment_tool');
        });
    }
};
