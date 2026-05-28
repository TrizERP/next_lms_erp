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
        Schema::table('lms_units', function (Blueprint $table) {
            $table->unsignedTinyInteger('planned_periods')
                ->nullable()
                ->after('total_marks');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lms_units', function (Blueprint $table) {
            $table->dropColumn('planned_periods');
        });
    }
};
