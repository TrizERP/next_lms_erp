<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-institute provider configuration (SMS, email, WhatsApp, push, payment,
 * biometric, bank). Secret values inside `config_json` are encrypted with
 * Laravel Crypt by the controller and masked in every API response.
 *
 * Re-runnable (guarded) and forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_integration_configs')) {
            return;
        }

        Schema::create('platform_integration_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id');
            $table->string('provider_key', 64);
            $table->string('display_name', 150);
            $table->string('category', 32);
            $table->string('description', 500)->nullable();
            $table->string('status', 16)->default('inactive');
            $table->longText('config_json')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_tested_by', 191)->nullable();
            $table->string('updated_by', 191)->nullable();
            $table->boolean('is_sample')->default(0);
            $table->timestamps();

            $table->unique(['sub_institute_id', 'provider_key'], 'pic_tenant_provider_unique');
            $table->index(['sub_institute_id', 'category'], 'pic_tenant_category_idx');
        });
    }

    public function down(): void
    {
        // Intentionally empty: configuration is never dropped by a rollback.
    }
};
