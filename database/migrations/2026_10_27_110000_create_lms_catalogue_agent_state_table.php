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
        if (!Schema::hasTable('lms_catalogue_agent_state')) {
            Schema::create('lms_catalogue_agent_state', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id')->unique();
                $table->string('agent_key', 64)->default('k12_course_catalogue')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('interval_seconds')->default(60);
                $table->dateTime('last_run_at')->nullable();
                $table->string('last_run_status', 32)->nullable();
                $table->dateTime('next_run_at')->nullable();
                $table->dateTime('last_heartbeat_at')->nullable();
                $table->integer('daemon_pid')->nullable();
                $table->string('latest_hash', 64)->nullable();
                $table->json('summary_counts')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_catalogue_agent_state');
    }
};
