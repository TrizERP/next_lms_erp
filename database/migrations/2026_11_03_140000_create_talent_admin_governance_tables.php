<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talent Management -> Administration & Governance: the tables behind the Templates, Integrations,
 * Configuration and Audit & Compliance tabs (Workflows already has its own tables, and Permissions
 * reads the existing user profiles).
 *
 *   php artisan migrate --path=database/migrations/2026_11_03_140000_create_talent_admin_governance_tables.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talent_admin_templates')) {
            Schema::create('talent_admin_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->string('name', 191);
                $table->string('category', 50)->index(); // Offer Letter | Appraisal Form | Onboarding Checklist | Exit Checklist | Email
                $table->string('module', 50)->nullable()->index();
                $table->string('status', 20)->default('Draft')->index(); // Active | Draft | Archived
                $table->string('version', 20)->default('1.0');
                $table->text('description')->nullable();
                $table->longText('body')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('talent_admin_integrations')) {
            Schema::create('talent_admin_integrations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->string('name', 191);
                $table->string('category', 50)->index(); // Job Board | Calendar | Email | HRIS | Background Check | E-Sign
                $table->string('status', 20)->default('Disconnected')->index(); // Connected | Disconnected | Error
                $table->text('description')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->string('last_sync_note', 191)->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('talent_admin_settings')) {
            Schema::create('talent_admin_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->string('setting_group', 50);
                $table->string('setting_key', 100);
                $table->text('setting_value')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['sub_institute_id', 'setting_key'], 'talent_admin_settings_tenant_key');
            });
        }

        if (! Schema::hasTable('talent_admin_audit_events')) {
            Schema::create('talent_admin_audit_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name', 191)->nullable();
                $table->string('module', 50)->index();
                $table->string('action', 50)->index(); // created | updated | deleted | connected | disconnected | settings_changed | ...
                $table->string('entity_type', 50)->nullable();
                $table->string('entity_name', 191)->nullable();
                $table->text('details')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_admin_audit_events');
        Schema::dropIfExists('talent_admin_settings');
        Schema::dropIfExists('talent_admin_integrations');
        Schema::dropIfExists('talent_admin_templates');
    }
};
