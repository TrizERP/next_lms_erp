<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New PAL → Administration — the architecture settings overlay.
 *
 * One table. It stores ONLY the values an administrator has actually changed;
 * the shipped defaults live in config/pal_architecture.php and are merged
 * underneath on every read (ArchitectureRegistry::subsystem).
 *
 * Storing overrides rather than a full copy is deliberate:
 *   - a deploy that revises a blueprint default reaches every tenant that has
 *     not overridden that key, instead of leaving stale copies behind;
 *   - an untouched estate has zero rows here and still works;
 *   - "reset to blueprint default" is a DELETE, not a re-seed.
 *
 * Scope is per institute. `sub_institute_id = 0` is the estate-wide fallback
 * used by super-admin edits, consulted when a tenant has no row of its own —
 * the same 0-default convention the Content Model overlay tables use.
 *
 * Additive only: nothing existing is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_architecture_settings')) {
            return;
        }

        Schema::create('pal_architecture_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sub_institute_id')->default(0);

            // Subsystem slug — 'mastery-model', 'ai-agents', … Validated against
            // the config on write, so an unknown slug can never be stored.
            $table->string('subsystem', 64);

            // The settings group inside that subsystem — 'bkt', 'bands',
            // 'agents', 'rubric'. One row per group, not per field: a group is
            // what the UI saves and what the engine reads, so it is the natural
            // unit of both concurrency and rollback.
            $table->string('settings_key', 64);

            // The overriding value. JSON because a group is a map or a list of
            // records, never a scalar.
            $table->json('value');

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // A tenant holds at most one override per group. sub_institute_id
            // is NOT NULL (0 = estate-wide), so this unique index actually
            // bites — a nullable column would let MySQL store duplicates.
            $table->unique(
                ['sub_institute_id', 'subsystem', 'settings_key'],
                'pal_arch_settings_scope_unique'
            );

            $table->index(['subsystem', 'settings_key'], 'pal_arch_settings_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_architecture_settings');
    }
};
