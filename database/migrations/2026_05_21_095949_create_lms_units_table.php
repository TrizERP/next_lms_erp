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
       Schema::create('lms_units', function (Blueprint $table) {

    $table->id();

    $table->unsignedBigInteger('curriculum_id');

    $table->unsignedTinyInteger('unit_number');

    $table->string('name',200);

    $table->unsignedSmallInteger('total_marks')->nullable();

    $table->timestamps();

    $table->foreign('curriculum_id')
        ->references('id')
        ->on('lms_curriculum')
        ->onDelete('cascade');

    $table->unique(
        ['curriculum_id','unit_number'],
        'uq_unit'
    );
});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_units');
    }
};
