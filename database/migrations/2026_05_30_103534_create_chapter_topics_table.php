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
        Schema::create('chapter_topics', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name');

            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('standard_id');
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedBigInteger('sub_institute_id');

            $table->unsignedBigInteger('concept_id')->nullable();
            $table->string('concept_name')->nullable();

            $table->unsignedBigInteger('mapping_type_id')->nullable();
            $table->unsignedBigInteger('mapping_value_id')->nullable();

            $table->dateTime('created_at')->nullable();

            // Optional indexes
            $table->index('subject_id');
            $table->index('standard_id');
            $table->index('chapter_id');
            $table->index('sub_institute_id');
            $table->index('concept_id');
            $table->index('mapping_type_id');
            $table->index('mapping_value_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_topics');
    }
};