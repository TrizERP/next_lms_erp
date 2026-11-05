<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers which Google Calendar event belongs to an interview, so rescheduling updates that event
 * instead of creating a second one.
 *
 *   php artisan migrate --path=database/migrations/2026_11_04_100000_add_google_event_id_to_talent_interview_schedules.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talent_interview_schedules') && ! Schema::hasColumn('talent_interview_schedules', 'google_event_id')) {
            Schema::table('talent_interview_schedules', function (Blueprint $table) {
                $table->string('google_event_id', 128)->nullable()->after('additional_notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('talent_interview_schedules') && Schema::hasColumn('talent_interview_schedules', 'google_event_id')) {
            Schema::table('talent_interview_schedules', function (Blueprint $table) {
                $table->dropColumn('google_event_id');
            });
        }
    }
};
