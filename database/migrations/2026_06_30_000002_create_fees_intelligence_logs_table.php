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
        Schema::create('fees_intelligence_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id');
            $table->integer('academic_year');
            $table->unsignedBigInteger('user_id')->nullable();
            
            // Agent identification
            $table->string('agent_id')->nullable(); // F-01, F-02, F-03
            $table->string('agent_action')->nullable(); // scan, forecast, etc.
            
            // Request data
            $table->json('request_data')->nullable();
            $table->json('selected_modules')->nullable();
            $table->text('extraction_prompt')->nullable();
            $table->text('intelligence_prompt')->nullable();
            
            // Response data
            $table->json('response_data')->nullable();
            $table->longText('ai_raw_response')->nullable();
            
            // Execution details
            $table->enum('execution_type', ['api_call', 'query', 'workflow', 'export'])->default('api_call');
            $table->enum('status', ['pending', 'success', 'failed', 'timeout'])->default('pending');
            $table->string('ai_model')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->decimal('execution_time_ms', 10, 2)->nullable();
            
            // Error handling
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();
            
            // Output reference
            $table->string('output_type')->nullable(); // report, notification, etc.
            $table->string('output_reference')->nullable(); // URL or file path
            
            $table->timestamps();
            
            // Indexes
            $table->index(['sub_institute_id', 'academic_year']);
            $table->index(['agent_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fees_intelligence_logs');
    }
};
