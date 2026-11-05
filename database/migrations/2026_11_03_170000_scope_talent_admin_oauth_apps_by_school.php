<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OAuth app keys can now be saved per school as well as platform wide. `sub_institute_id` 0 is the
 * platform-wide entry a Super Admin saves; a school's own admin saves keys for that school only, so no
 * school can change how another school connects.
 *
 *   php artisan migrate --path=database/migrations/2026_11_03_170000_scope_talent_admin_oauth_apps_by_school.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talent_admin_oauth_apps') || Schema::hasColumn('talent_admin_oauth_apps', 'sub_institute_id')) {
            return;
        }

        Schema::table('talent_admin_oauth_apps', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_institute_id')->default(0)->after('id');
            $table->dropUnique(['provider']);
            $table->unique(['provider', 'sub_institute_id'], 'talent_oauth_apps_provider_school');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('talent_admin_oauth_apps') || ! Schema::hasColumn('talent_admin_oauth_apps', 'sub_institute_id')) {
            return;
        }

        Schema::table('talent_admin_oauth_apps', function (Blueprint $table) {
            $table->dropUnique('talent_oauth_apps_provider_school');
            $table->dropColumn('sub_institute_id');
            $table->unique('provider');
        });
    }
};
