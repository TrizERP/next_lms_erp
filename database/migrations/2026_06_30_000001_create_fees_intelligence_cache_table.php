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
        Schema::create('fees_intelligence_cache', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id');
            $table->integer('academic_year');
            $table->unsignedBigInteger('user_id')->nullable();
            
            // Cache data
            $table->json('dashboard_stats')->nullable();
            $table->json('collection_data')->nullable();
            $table->json('defaulters_data')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('action_items')->nullable();
            $table->json('cross_module_workflows')->nullable();
            $table->json('ai_agents')->nullable();
            $table->json('module_integration')->nullable();
            $table->json('ai_insights')->nullable();
            
            // Generation metadata
            $table->string('extraction_prompt')->nullable();
            $table->string('intelligence_prompt')->nullable();
            $table->json('selected_modules')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index(['sub_institute_id', 'academic_year']);
            $table->index(['sub_institute_id', 'academic_year', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fees_intelligence_cache');
    }
};
