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
        Schema::create('lms_assessment_typology', function (Blueprint $table) {

    $table->id();

    $table->unsignedBigInteger('curriculum_id');

    $table->string('category',150);

    $table->json('bloom_levels');

    $table->unsignedSmallInteger('total_marks');

    $table->decimal('weightage_pct',5,2);

    $table->timestamps();

    $table->foreign('curriculum_id')
        ->references('id')
        ->on('lms_curriculum')
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
        Schema::dropIfExists('lms_assessment_typology');
    }
};
