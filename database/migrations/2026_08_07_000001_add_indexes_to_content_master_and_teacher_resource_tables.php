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
        Schema::table('content_master', function (Blueprint $table) {
            if (!Schema::hasIndex('content_master', 'idx_content_master_chapter_id')) {
                $table->index('chapter_id', 'idx_content_master_chapter_id');
            }
            if (!Schema::hasIndex('content_master', 'idx_content_master_concept_id')) {
                $table->index('concept_id', 'idx_content_master_concept_id');
            }
            if (!Schema::hasIndex('content_master', 'idx_content_master_content_category')) {
                $table->index('content_category', 'idx_content_master_content_category');
            }
            if (!Schema::hasIndex('content_master', 'idx_content_master_sub_institute_id')) {
                $table->index('sub_institute_id', 'idx_content_master_sub_institute_id');
            }
            if (!Schema::hasIndex('content_master', 'idx_content_master_standard_id')) {
                $table->index('standard_id', 'idx_content_master_standard_id');
            }
            if (!Schema::hasIndex('content_master', 'idx_content_master_subject_id')) {
                $table->index('subject_id', 'idx_content_master_subject_id');
            }
        });

        Schema::table('lms_teacher_resource', function (Blueprint $table) {
            if (!Schema::hasIndex('lms_teacher_resource', 'idx_lms_teacher_resource_chapter_id')) {
                $table->index('chapter_id', 'idx_lms_teacher_resource_chapter_id');
            }
            if (!Schema::hasIndex('lms_teacher_resource', 'idx_lms_teacher_resource_sub_institute_id')) {
                $table->index('sub_institute_id', 'idx_lms_teacher_resource_sub_institute_id');
            }
            if (!Schema::hasIndex('lms_teacher_resource', 'idx_lms_teacher_resource_standard_id')) {
                $table->index('standard_id', 'idx_lms_teacher_resource_standard_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_master', function (Blueprint $table) {
            if (Schema::hasIndex('content_master', 'idx_content_master_chapter_id')) {
                $table->dropIndex('idx_content_master_chapter_id');
            }
            if (Schema::hasIndex('content_master', 'idx_content_master_concept_id')) {
                $table->dropIndex('idx_content_master_concept_id');
            }
            if (Schema::hasIndex('content_master', 'idx_content_master_content_category')) {
                $table->dropIndex('idx_content_master_content_category');
            }
            if (Schema::hasIndex('content_master', 'idx_content_master_sub_institute_id')) {
                $table->dropIndex('idx_content_master_sub_institute_id');
            }
            if (Schema::hasIndex('content_master', 'idx_content_master_standard_id')) {
                $table->dropIndex('idx_content_master_standard_id');
            }
            if (Schema::hasIndex('content_master', 'idx_content_master_subject_id')) {
                $table->dropIndex('idx_content_master_subject_id');
            }
        });

        Schema::table('lms_teacher_resource', function (Blueprint $table) {
            if (Schema::hasIndex('lms_teacher_resource', 'idx_lms_teacher_resource_chapter_id')) {
                $table->dropIndex('idx_lms_teacher_resource_chapter_id');
            }
            if (Schema::hasIndex('lms_teacher_resource', 'idx_lms_teacher_resource_sub_institute_id')) {
                $table->dropIndex('idx_lms_teacher_resource_sub_institute_id');
            }
            if (Schema::hasIndex('lms_teacher_resource', 'idx_lms_teacher_resource_standard_id')) {
                $table->dropIndex('idx_lms_teacher_resource_standard_id');
            }
        });
    }
};
