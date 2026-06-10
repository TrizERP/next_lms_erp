<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lms_concept', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_id')->nullable();

            $table->string('name');

            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('standard_id');
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedBigInteger('sub_institute_id');
            $table->integer('difficulty_level')->nullable();

            $table->string('bloom_level')->nullable();

            $table->string('pedagogy_tag')->nullable();

            $table->float('mastery_threshold')->default(0);

            $table->integer('estimated_mastery_minutes')->nullable();

            $table->integer('syear');

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_concept');
    }
};
