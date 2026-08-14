<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pal_unified_learning_units', function (Blueprint $table) {
            $table->id();
            $table->string('ulu_id')->unique();
            $table->string('title');
            $table->unsignedTinyInteger('grade')->default(1);
            $table->string('subject');
            $table->string('academic_concept');
            $table->string('sub_concept')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->unsignedInteger('duration_minutes')->default(10);
            $table->string('language')->default('en');
            $table->string('cultural_context')->nullable();
            $table->string('social_mode')->nullable();
            $table->string('pedagogy_tag')->nullable();
            $table->string('h5p_type')->nullable();
            $table->string('casel_domain')->nullable();
            $table->string('ngss_practice')->nullable();
            $table->string('ncdg_goal')->nullable();
            $table->string('riasec_signal')->nullable();
            $table->string('career_cluster')->nullable();
            $table->string('real_skill_name')->nullable();
            $table->float('mastery_gate')->default(0.70);
            $table->json('academic_core')->nullable();
            $table->json('sel_layer')->nullable();
            $table->json('stem_layer')->nullable();
            $table->json('career_layer')->nullable();
            $table->json('real_skill')->nullable();
            $table->json('scenario')->nullable();
            $table->json('branches')->nullable();
            $table->json('reflections')->nullable();
            $table->json('delivery')->nullable();
            $table->json('qa_checks')->nullable();
            $table->json('analytics')->nullable();
            $table->json('optimization_flags')->nullable();
            $table->json('cross_domain_links')->nullable();
            $table->string('graph_sync_status')->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'subject', 'grade'], 'pal_ulu_status_subject_grade_idx');
            $table->index(['casel_domain', 'ngss_practice', 'career_cluster'], 'pal_ulu_framework_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_unified_learning_units');
    }
};
