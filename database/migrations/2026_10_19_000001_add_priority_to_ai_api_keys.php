<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One nullable column so an administrator can say which provider a module tries first.
 *
 * WHY A COLUMN AND NOT A CONVENTION
 *
 * A module can now hold several configurations — one per provider — and the platform falls back
 * from the first to the next when a provider fails or its quota is spent. "First" has to be
 * stored somewhere an administrator can change it. Every existing column already means
 * something else, so this adds the one that does not exist.
 *
 * NOTHING CHANGES UNTIL IT IS USED
 *
 * The column is nullable and not backfilled. The application reads it only when it exists, and
 * treats NULL as "unranked", ordering unranked rows newest-first exactly as before. Until the
 * column is added, the admin screen says priority cannot be saved and orders by the same rule.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_10_19_000001_add_priority_to_ai_api_keys.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_api_keys') || Schema::hasColumn('ai_api_keys', 'priority')) {
            return;
        }

        Schema::table('ai_api_keys', function (Blueprint $table) {
            // 1 = first choice. NULL = unranked, which sorts after every ranked row.
            $table->unsignedSmallInteger('priority')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_api_keys') && Schema::hasColumn('ai_api_keys', 'priority')) {
            Schema::table('ai_api_keys', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }
    }
};
