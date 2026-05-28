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
        Schema::table('chapter_master', function (Blueprint $table) {

    $table->unsignedBigInteger('unit_id')->nullable()->after('id');

    $table->unsignedTinyInteger('planned_periods')
        ->nullable()
        ->after('chapter_name');

    $table->json('key_concepts')
        ->nullable()
        ->after('planned_periods');

 

    $table->foreign('unit_id')
        ->references('id')
        ->on('lms_units')
        ->onDelete('cascade');
});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chapter_master', function (Blueprint $table) {
            //
        });
    }
};
