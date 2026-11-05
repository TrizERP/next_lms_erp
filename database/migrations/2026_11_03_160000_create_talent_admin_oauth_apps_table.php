<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide OAuth app credentials for the Talent Management integrations (Google, Microsoft,
 * LinkedIn, DocuSign). Registered once by a Super Admin from the Integrations tab, so nobody has to
 * edit .env; every school then just clicks Connect. The secret is stored encrypted.
 *
 *   php artisan migrate --path=database/migrations/2026_11_03_160000_create_talent_admin_oauth_apps_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talent_admin_oauth_apps')) {
            Schema::create('talent_admin_oauth_apps', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 30)->unique();
                $table->string('client_id', 255);
                $table->text('client_secret'); // Crypt::encryptString
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_admin_oauth_apps');
    }
};
