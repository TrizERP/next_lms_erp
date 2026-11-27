<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Approved, money hasn't moved yet" needs its own authoritative place —
 * `platform_workflow_runs.status` belongs to the shared approval engine
 * (other modules read it too) and `fees_collect` has no link back to an
 * approval at all. One row per approved concession/refund request, created
 * once (FeesConcessionGrantService::recordFromRun, idempotent on `run_id`)
 * and updated as it is applied — possibly across more than one receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fees_concession_grants')) {
            return;
        }

        Schema::create('fees_concession_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id');
            $table->unsignedBigInteger('run_id');
            $table->string('flow_key', 191);
            $table->string('kind', 16)->comment('concession | refund');
            $table->unsignedBigInteger('student_id');
            $table->integer('syear')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('applied_amount', 12, 2)->default(0);
            $table->json('refund_heads')->nullable()->comment('refund only: {particular_id: amount}');
            $table->string('status', 32)->default('approved_pending_application')->comment('approved_pending_application | applied');
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by_name', 191)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->json('applied_reference')->nullable();
            $table->timestamps();

            $table->unique('run_id', 'fcg_run_unique');
            $table->index(['sub_institute_id', 'status'], 'fcg_tenant_status_idx');
            $table->index(['sub_institute_id', 'student_id'], 'fcg_tenant_student_idx');
        });
    }

    public function down(): void
    {
        // Intentionally empty: shared database, never drop tables from a rollback.
    }
};
