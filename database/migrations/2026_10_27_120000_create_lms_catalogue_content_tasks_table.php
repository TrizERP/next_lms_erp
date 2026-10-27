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
        if (!Schema::hasTable('lms_catalogue_content_tasks')) {
            Schema::create('lms_catalogue_content_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('standard_id')->index();
                $table->string('standard_name', 191)->nullable();
                $table->unsignedBigInteger('subject_id')->index();
                $table->string('subject_name', 191)->nullable();
                $table->unsignedBigInteger('chapter_id')->index();
                $table->string('chapter_name', 191)->nullable();
                $table->unsignedBigInteger('topic_id')->index();
                $table->string('topic_name', 191);
                $table->string('status', 32)->default('pending')->index(); // pending, verified_existing, generated_new, failed
                $table->unsignedBigInteger('content_id')->nullable()->index(); // content_master.id
                $table->unsignedBigInteger('concept_id')->nullable()->index(); // lms_concept.id
                $table->string('action_taken', 64)->default('pending');
                $table->string('content_title', 250)->nullable();
                $table->text('content_preview')->nullable();
                $table->timestamps();

                $table->unique(['sub_institute_id', 'topic_id'], 'sub_inst_topic_unique');
            });
        }

        if (Schema::hasTable('lms_catalogue_agent_state')) {
            Schema::table('lms_catalogue_agent_state', function (Blueprint $table) {
                if (!Schema::hasColumn('lms_catalogue_agent_state', 'auto_generate_content')) {
                    $table->boolean('auto_generate_content')->default(true)->after('is_active');
                }
                if (!Schema::hasColumn('lms_catalogue_agent_state', 'content_agent_status')) {
                    $table->string('content_agent_status', 32)->default('active')->after('auto_generate_content');
                }
                if (!Schema::hasColumn('lms_catalogue_agent_state', 'content_tasks_completed')) {
                    $table->integer('content_tasks_completed')->default(0)->after('content_agent_status');
                }
                if (!Schema::hasColumn('lms_catalogue_agent_state', 'content_tasks_generated')) {
                    $table->integer('content_tasks_generated')->default(0)->after('content_tasks_completed');
                }
                if (!Schema::hasColumn('lms_catalogue_agent_state', 'content_tasks_verified')) {
                    $table->integer('content_tasks_verified')->default(0)->after('content_tasks_generated');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_catalogue_content_tasks');
    }
};
