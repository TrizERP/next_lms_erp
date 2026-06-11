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
        Schema::create('semantic_intelligence', function (Blueprint $table) {
            $table->id();
            $table->integer('extraction_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('subject_id')->nullable();
            $table->integer('chapter_id')->nullable();
            $table->string('subject_name')->nullable();
            $table->integer('standard')->nullable();
            $table->integer('chapter_number')->nullable();
            $table->string('learning_objective')->nullable();
            $table->integer('total_topics')->nullable();
            $table->json('full_intelligence_json')->nullable();
            $table->string('llm_model')->nullable();
            $table->integer('input_token')->nullable();
            $table->integer('output_token')->nullable();
            $table->string('quality_flag')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semantic_intelligence');
    }
};
