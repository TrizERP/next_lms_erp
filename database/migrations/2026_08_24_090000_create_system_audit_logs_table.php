<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic WHO/WHAT/WHEN/OLD-VALUE/NEW-VALUE trail for sensitive operations
 * that previously had none: fee collection/cancellation/refund, marks entry,
 * student status changes, and permission (individual/groupwise rights)
 * changes. Written to via App\Models\AuditLog::record().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('module', 60)->index();
            $table->string('action', 60)->index();
            $table->string('entity_type', 80)->nullable();
            $table->string('entity_id', 60)->nullable()->index();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_audit_logs');
    }
};
