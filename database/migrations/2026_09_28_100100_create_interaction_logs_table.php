<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A unified staff/parent/student touchpoint log — calls, meetings, notes and follow-ups.
 *
 * Nothing like this exists in this estate today. `check_ai_stack_coverage.php` has
 * carried `interactions` on its deliberately-unbound list since it was written, with the
 * reason "no interactions table in this estate" — this migration is that table, added so
 * the Interactions AI Stack has real, staff-populated records to read rather than nothing.
 *
 * GENUINELY EMPTY UNTIL A PERSON LOGS SOMETHING. No seed rows, no sample data. A school
 * that has not started using this yet sees the correct empty state, not a populated demo.
 *
 * Column and index shape follows 2026_09_27_100000_create_ai_module_model_bindings_table.php
 * — the newest table in this estate at the time this was written: snake_case, nullable
 * indexed sub_institute_id, timestamps(), no legacy uppercase columns.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_28_100100_create_interaction_logs_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interaction_logs')) {
            return;
        }

        Schema::create('interaction_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();

            // Who logged it, and who it is about. `related_type` is a small closed set
            // rather than a polymorphic model binding, because the record is a log entry
            // about a person the ERP already knows by id in one of a few existing tables —
            // it does not need its own relationship graph.
            $table->unsignedBigInteger('staff_id');
            $table->string('related_type', 20); // student | parent | staff | visitor
            $table->unsignedBigInteger('related_id');

            $table->string('interaction_type', 20); // call | meeting | note | follow_up
            $table->string('subject', 191);
            $table->text('notes')->nullable();

            $table->timestamp('occurred_at');
            $table->date('follow_up_date')->nullable();
            $table->string('status', 20)->default('open'); // open | closed

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index(['sub_institute_id', 'related_type', 'related_id'], 'interaction_logs_subject_idx');
            $table->index(['sub_institute_id', 'status'], 'interaction_logs_status_idx');
            $table->index(['sub_institute_id', 'occurred_at'], 'interaction_logs_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_logs');
    }
};
