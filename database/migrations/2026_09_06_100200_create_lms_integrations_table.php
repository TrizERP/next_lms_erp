<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third-party integrations connected to the LMS, for G2G-LMS Administration
 * & Governance.
 *
 * Ported from hp_erp's `2026_07_29_100002_create_lms_integrations_table.php`
 * (`App\Http\Controllers\Api\LmsPartnerController`). New model - nothing in
 * this schema records which integrations are connected.
 *
 * IMPORTANT - no secrets here. `config` holds non-sensitive settings only
 * (endpoints, scopes, sync options). Access tokens and API keys stay with
 * the OAuth provider or server-side config.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_integrations')) {
            return;
        }
Schema::create('lms_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            /** Machine name: 'google', 'zoom', 'teams', 'scorm_cloud'. */
            $table->string('provider', 100);
            $table->string('display_name', 191);
            $table->string('category', 50)->nullable();
            $table->text('description')->nullable();

            /** 'connected', 'disconnected', 'error'. */
            $table->string('status', 20)->default('disconnected');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();

            /** Non-sensitive settings only. See the note above. */
            $table->longText('config')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sub_institute_id', 'provider'], 'lms_integrations_tenant_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_integrations');
    }
};
