<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `talent_onboarding_activity_log` is defined in
 * `2026_08_18_111000_create_talent_onboarding_tables.php` alongside five
 * sibling tables, but on this environment only this one table was found
 * missing (the other five - journeys, tasks, documents, journey_stages,
 * notes - all exist) despite that migration being recorded as run. Recreates
 * it with the exact same column set rather than editing the original
 * (already-applied) migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talent_onboarding_activity_log')) {
            return;
        }

        Schema::create('talent_onboarding_activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('actor_name', 191)->nullable();
            $table->string('action', 191)->index();
            $table->text('description')->nullable();
            $table->string('subject_type', 100)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('subject_name', 191)->nullable();
            $table->longText('changes')->nullable();
            $table->unsignedBigInteger('journey_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_onboarding_activity_log');
    }
};
