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
        Schema::table('syllabus', function (Blueprint $table) {
            //
            $table->bigInteger('curriculum_id')->after('date_')->nullable();
            $table->mediumText('objectives')->after('curriculum_id')->nullable();
            $table->mediumText('learning_outcomes')->after('objectives')->nullable();
            $table->mediumText('suggested_materials')->after('learning_outcomes')->nullable();
            $table->decimal('progress_tracking', 5, 2)->after('suggested_materials')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('syllabus', function (Blueprint $table) {
            //
            $table->dropColumn('curriculum_id');
            $table->dropColumn('objectives');
            $table->dropColumn('learning_outcomes');
            $table->dropColumn('suggested_materials');
            $table->dropColumn('progress_tracking');
        });
    }
};
