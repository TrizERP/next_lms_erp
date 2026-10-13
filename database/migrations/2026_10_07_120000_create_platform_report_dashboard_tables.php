<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the shared reporting engine (scheduled exports) and the dashboard
 * engine (per-user widget layout). Guarded so it can be run again safely;
 * down() drops nothing because the database is shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_report_schedules')) {
            Schema::create('platform_report_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->string('report_key', 191);
                $table->string('name', 191);
                $table->string('frequency', 16)->default('daily')->comment('daily | weekly | monthly');
                $table->json('filters')->nullable();
                $table->json('recipients')->comment('email addresses');
                $table->boolean('enabled')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->string('last_run_status', 16)->nullable()->comment('ok | failed');
                $table->string('last_run_message', 500)->nullable();
                $table->unsignedBigInteger('last_file_id')->nullable()->comment('platform_file_attachments.id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('is_sample')->default(false);
                $table->timestamps();
                $table->index(['sub_institute_id', 'enabled'], 'prs_tenant_enabled_idx');
            });
        }

        if (! Schema::hasTable('platform_dashboard_layouts')) {
            Schema::create('platform_dashboard_layouts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->unsignedBigInteger('user_id');
                $table->json('layout')->comment('ordered list of {key, hidden}');
                $table->timestamps();
                $table->unique(['sub_institute_id', 'user_id'], 'pdl_tenant_user_uq');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: shared database, never drop tables from a rollback.
    }
};
