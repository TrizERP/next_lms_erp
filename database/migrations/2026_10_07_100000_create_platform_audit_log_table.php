<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared, append-only audit trail every module writes to through
 * App\Services\Platform\AuditTrail::record().
 *
 * APPEND-ONLY BY CONSTRUCTION: nothing in the codebase updates or deletes these
 * rows, and no route exists that could. `actor_name` is denormalised so the entry
 * still reads correctly after the user is renamed or removed.
 *
 * Re-runnable (guarded) and forward-only: no down() drop, matching the other
 * platform_* migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_audit_log')) {
            return;
        }

        Schema::create('platform_audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id');
            $table->string('module', 64);
            $table->string('component', 128)->nullable();
            $table->string('action', 64);
            $table->string('entity_type', 128)->nullable();
            $table->string('entity_id', 128)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_name', 191)->nullable();
            $table->longText('before_json')->nullable();
            $table->longText('after_json')->nullable();
            $table->string('ip', 64)->nullable();
            $table->boolean('is_sample')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sub_institute_id', 'created_at'], 'pal_tenant_created_idx');
            $table->index(['sub_institute_id', 'module'], 'pal_tenant_module_idx');
        });
    }

    public function down(): void
    {
        // Intentionally empty: an audit table is never dropped by a rollback.
    }
};
