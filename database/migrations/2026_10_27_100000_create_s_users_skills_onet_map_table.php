<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crosswalk between an institute's People & Competency skills
 * (s_users_skills, free text) and O*NET content-model elements
 * (onet_content_model_reference), used by the Career Counseling
 * "Suggested mentors" feature.
 *
 * The two vocabularies share no key, so a row exists ONLY when an HR user has
 * confirmed it. Nothing in the application ever inserts a row by name
 * matching. Additive only: creates one new table, touches nothing else.
 *
 * Run it on its own, never as a bare `php artisan migrate`:
 *   php artisan migrate --path=database/migrations/2026_10_27_100000_create_s_users_skills_onet_map_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('s_users_skills_onet_map')) {
            return;
        }

        Schema::create('s_users_skills_onet_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id');
            // s_users_skills.id is a bigint unsigned.
            $table->unsignedBigInteger('skill_id');
            // Same type as onet_content_model_reference.element_id (varchar(20)).
            $table->string('element_id', 20);
            $table->string('source', 30)->default('manual');
            // tbluser.id is int unsigned.
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamps();

            // No FKs: tbluser/onet_* are legacy tables with differing engines
            // and collations; the service joins in PHP.
            $table->unique(['sub_institute_id', 'skill_id', 'element_id'], 'uq_skill_onet_map');
            $table->index(['sub_institute_id', 'element_id'], 'idx_skill_onet_map_element');
            $table->index(['sub_institute_id', 'skill_id'], 'idx_skill_onet_map_skill');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_users_skills_onet_map');
    }
};
