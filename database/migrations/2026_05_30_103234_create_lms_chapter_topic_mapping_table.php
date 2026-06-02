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
        Schema::create('lms_chapter_topic_mapping', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('sub_institute_id');
            $table->unsignedBigInteger('standard_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('chapter_id');

            $table->unsignedBigInteger('concept_id')->nullable();
            $table->string('concept_name')->nullable();

            $table->unsignedBigInteger('topic_id')->nullable();

            $table->unsignedBigInteger('mapping_type_id')->nullable();
            $table->unsignedBigInteger('mapping_value_id')->nullable();

            $table->timestamps();

            // Optional indexes
            $table->index('sub_institute_id');
            $table->index('standard_id');
            $table->index('subject_id');
            $table->index('chapter_id');
            $table->index('concept_id');
            $table->index('topic_id');
            $table->index('mapping_type_id');
            $table->index('mapping_value_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_chapter_topic_mapping');
    }
};