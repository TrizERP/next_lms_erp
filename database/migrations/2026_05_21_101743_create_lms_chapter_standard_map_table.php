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
        Schema::create('lms_chapter_standard_map', function (Blueprint $table) {
                $table->id();


    $table->unsignedBigInteger('chapter_id');

    $table->unsignedBigInteger('competency_standard_id');

    $table->primary(
        ['chapter_id','competency_standard_id']
    );

    $table->foreign('chapter_id')
        ->references('id')
        ->on('chapter_master')
        ->onDelete('cascade');

    $table->foreign('competency_standard_id')
        ->references('id')
        ->on('lms_competency_standards')
        ->onDelete('cascade');

    $table->index(
        'competency_standard_id',
        'idx_csm_standard'
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
        Schema::dropIfExists('lms_chapter_standard_map');
    }
};
