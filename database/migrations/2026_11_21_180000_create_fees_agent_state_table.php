<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Continuous-monitoring state for the Fees Agentic Intelligence capability
 * — mirrors `lms_pal_agent_state`'s own shape (same columns, same intent:
 * one row per tenant tracking on/off, polling interval, and the last
 * sweep's outcome). A separate table rather than reusing PAL's: this one
 * tracks TWO governed agents per tenant (`k12_fees`, `k12_fees_predictive`,
 * run via AgentRunner/ai_agents), not PAL's own bespoke tool pipeline, so
 * `agent_key` here is always 'fees_pipeline' (both agents run together)
 * rather than a single tool key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fees_agent_state')) {
            return;
        }

        Schema::create('fees_agent_state', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id');
            $table->string('agent_key', 64)->default('fees_pipeline');
            $table->boolean('is_active')->default(false);
            $table->integer('interval_seconds')->default(300);
            $table->dateTime('last_run_at')->nullable();
            $table->string('last_run_status', 32)->nullable();
            $table->dateTime('next_run_at')->nullable();
            $table->dateTime('last_heartbeat_at')->nullable();
            $table->longText('summary_counts')->nullable();
            $table->timestamps();

            $table->unique(['sub_institute_id', 'agent_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fees_agent_state');
    }
};
