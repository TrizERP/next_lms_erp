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
        if (!Schema::hasTable('lms_catalogue_hierarchy_validations')) {
            Schema::create('lms_catalogue_hierarchy_validations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->string('agent_key', 64)->default('k12_course_catalogue')->index();
                $table->string('status', 32)->default('completed')->index();
                $table->string('data_hash', 64)->index();
                $table->integer('standards_count')->default(0);
                $table->integer('subjects_count')->default(0);
                $table->integer('chapters_count')->default(0);
                $table->integer('topics_count')->default(0);
                $table->integer('intelligence_count')->default(0);
                $table->integer('complete_hierarchies_count')->default(0);
                $table->integer('issues_count')->default(0);
                $table->integer('missing_subjects_count')->default(0);
                $table->integer('missing_chapters_count')->default(0);
                $table->integer('missing_topics_count')->default(0);
                $table->integer('missing_intelligence_count')->default(0);
                $table->integer('broken_mappings_count')->default(0);
                $table->json('summary')->nullable();
                $table->json('issues')->nullable();
                $table->json('newly_discovered')->nullable();
                $table->json('resolved')->nullable();
                $table->dateTime('started_at');
                $table->dateTime('finished_at')->nullable();
                $table->integer('duration_ms')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_catalogue_hierarchy_validations');
    }
};
