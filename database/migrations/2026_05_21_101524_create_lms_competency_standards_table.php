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
       Schema::create('lms_competency_standards', function (Blueprint $table) {

    $table->id();

    $table->unsignedBigInteger('curriculum_id');

    $table->unsignedBigInteger('parent_id')->nullable();

    $table->string('code',20);

    $table->text('description');

    $table->string('domain_tag',50)->nullable();

    $table->timestamps();

    $table->foreign('curriculum_id')
        ->references('id')
        ->on('lms_curriculum')
        ->onDelete('cascade');

    $table->foreign('parent_id')
        ->references('id')
        ->on('lms_competency_standards')
        ->onDelete('cascade');

    $table->unique(
        ['curriculum_id','code'],
        'uq_competency_standard'
    );

    $table->index('parent_id','idx_std_parent');
});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_competency_standards');
    }
};
