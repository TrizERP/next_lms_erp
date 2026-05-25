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
       Schema::create('lms_learning_outcomes', function (Blueprint $table) {

    $table->id();

    $table->unsignedBigInteger('chapter_id');

    $table->text('outcome');

    $table->enum('bloom_level',[
        'remember',
        'understand',
        'apply',
        'analyse',
        'evaluate',
        'create'
    ]);

    $table->unsignedTinyInteger('sort_order')
        ->default(0);

    $table->timestamps();

    $table->foreign('chapter_id')
        ->references('id')
        ->on('chapter_master')
        ->onDelete('cascade');

    $table->index('bloom_level','idx_lo_bloom');
});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_learning_outcomes');
    }
};
