<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Content Learning Resource Metadata — the three-field classification
     * the backlog calls "purpose, audience, delivery_mode".
     *
     * Sidecar over content_master so the live content tables are never touched
     * (same CONTENT LAW C2 pattern used by pal_content_metadata).
     */
    public function up(): void
    {
        if (Schema::hasTable('lms_content_resource_metadata')) {
            return;
        }

        Schema::create('lms_content_resource_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('content_master_id');
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->string('scope', 16)->default('tenant');

            $table->string('purpose', 64)->nullable()->comment('Why this resource exists: instruction, practice, assessment, reference, enrichment, remediation');
            $table->string('audience', 64)->nullable()->comment('Who it is intended for: teacher, student, class, school, pal');
            $table->string('delivery_mode', 64)->nullable()->comment('How it is used: before_class, during_class, after_class, self_study, assessment, reference');

            $table->json('metadata')->nullable()->comment('Additional classification key/value pairs');
            $table->unsignedTinyInteger('version')->default(1);
            $table->string('quality_status', 24)->default('draft')->comment('draft, reviewed, approved, deprecated');
            $table->string('tagged_by', 16)->default('human')->comment('human, ai, imported, derived');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('last_reviewed')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->unique(['content_master_id', 'sub_institute_id', 'scope'], 'lms_crm_tenant_unique');
            $table->index(['sub_institute_id', 'quality_status'], 'lms_crm_status_idx');
            $table->index(['purpose', 'audience', 'delivery_mode'], 'lms_crm_classification_idx');
            $table->foreign('content_master_id')->references('id')->on('content_master')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_content_resource_metadata');
    }
};
