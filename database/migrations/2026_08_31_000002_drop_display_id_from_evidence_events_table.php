<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reverts 2026_08_31_000001_add_display_id_to_evidence_events_table — the
 * product decision changed to converting evidence_id itself to an
 * auto-increment integer (see the following migration) instead of adding a
 * separate display_id column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_events', function (Blueprint $table) {
            $table->dropColumn('display_id');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE evidence_events ADD COLUMN display_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE');
    }
};
